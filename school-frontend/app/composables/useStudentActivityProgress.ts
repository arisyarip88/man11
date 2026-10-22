import { ref, computed } from 'vue'

export const useStudentActivityProgress = () => {
  const { user } = useAuth()

  // Storage key for downloaded materials & completed quizzes per student
  const getStorageKey = (prefix: string) => {
    const uid = user.value?.id || 'guest'
    return `${prefix}_${uid}`
  }

  // Downloaded material IDs (reactive shared state)
  const downloadedMaterialIds = useState<number[]>('student_downloaded_materials', () => [])
  // Locally submitted quiz IDs (reactive shared state for instant reactivity)
  const locallySubmittedQuizIds = useState<number[]>('student_submitted_quizzes', () => [])

  const loadFromStorage = () => {
    if (process.client) {
      try {
        const matKey = getStorageKey('student_downloaded_materials')
        const rawMat = localStorage.getItem(matKey)
        if (rawMat) {
          downloadedMaterialIds.value = JSON.parse(rawMat)
        }

        const quizKey = getStorageKey('student_submitted_quizzes')
        const rawQuiz = localStorage.getItem(quizKey)
        if (rawQuiz) {
          locallySubmittedQuizIds.value = JSON.parse(rawQuiz)
        }
      } catch (e) {
        console.error('Error loading activity progress from storage:', e)
      }
    }
  }

  // Initialize on client if needed
  if (process.client && downloadedMaterialIds.value.length === 0) {
    loadFromStorage()
  }

  const markMaterialDownloaded = (materialId: number | string) => {
    const id = Number(materialId)
    if (!id) return
    if (!downloadedMaterialIds.value.includes(id)) {
      downloadedMaterialIds.value.push(id)
      if (process.client) {
        try {
          const matKey = getStorageKey('student_downloaded_materials')
          localStorage.setItem(matKey, JSON.stringify(downloadedMaterialIds.value))
        } catch (e) {
          console.error('Error saving downloaded material:', e)
        }
      }
    }
  }

  const markQuizCompleted = (quizId: number | string) => {
    const id = Number(quizId)
    if (!id) return
    if (!locallySubmittedQuizIds.value.includes(id)) {
      locallySubmittedQuizIds.value.push(id)
      if (process.client) {
        try {
          const quizKey = getStorageKey('student_submitted_quizzes')
          localStorage.setItem(quizKey, JSON.stringify(locallySubmittedQuizIds.value))
        } catch (e) {
          console.error('Error saving completed quiz:', e)
        }
      }
    }
  }

  const isMaterialDownloaded = (materialId: number | string): boolean => {
    return downloadedMaterialIds.value.includes(Number(materialId))
  }

  const isQuizCompleted = (quiz: any): boolean => {
    if (!quiz) return false
    const id = Number(quiz.id)
    if (locallySubmittedQuizIds.value.includes(id)) return true
    if (quiz.submissions && Array.isArray(quiz.submissions) && quiz.submissions.length > 0) return true
    if (quiz.my_submission || quiz.submission) return true
    if (typeof quiz.submissions_count === 'number' && quiz.submissions_count > 0) return true
    return false
  }

  const isDiscussionDone = (meet: any): boolean => {
    if (!meet?.discussions || !Array.isArray(meet.discussions) || meet.discussions.length === 0) return false
    const studentId = user.value?.student?.id
    const userId = user.value?.id
    return meet.discussions.some((d: any) =>
      (studentId && d.student_id === studentId) ||
      (userId && d.user_id === userId)
    )
  }

  const isActivityDone = (meet: any, activityKey: string): boolean => {
    if (!meet) return false

    if (activityKey === 'pretest') {
      const pretests = (meet.quizzes || []).filter((q: any) => q.activity_category === 'pretest')
      return pretests.length > 0 && pretests.some(isQuizCompleted)
    }

    if (activityKey === 'material') {
      const materials = meet.materials || []
      return materials.length > 0 && materials.some((m: any) => isMaterialDownloaded(m.id))
    }

    if (activityKey === 'discussion') {
      return isDiscussionDone(meet)
    }

    if (activityKey === 'quiz') {
      const quizzes = (meet.quizzes || []).filter((q: any) =>
        q.activity_category === 'quiz' ||
        (!q.activity_category && (q.quiz_type === 'multiple_choice' || q.quiz_type === 'quiz'))
      )
      return quizzes.length > 0 && quizzes.some(isQuizCompleted)
    }

    if (activityKey === 'assignment') {
      const assignments = (meet.quizzes || []).filter((q: any) =>
        q.activity_category === 'assignment' ||
        (!q.activity_category && (q.quiz_type === 'assignment' || q.quiz_type === 'essay'))
      )
      return assignments.length > 0 && assignments.some(isQuizCompleted)
    }

    if (activityKey === 'posttest') {
      const posttests = (meet.quizzes || []).filter((q: any) => q.activity_category === 'posttest')
      return posttests.length > 0 && posttests.some(isQuizCompleted)
    }

    return false
  }

  return {
    downloadedMaterialIds,
    locallySubmittedQuizIds,
    markMaterialDownloaded,
    markQuizCompleted,
    isMaterialDownloaded,
    isQuizCompleted,
    isDiscussionDone,
    isActivityDone,
    loadFromStorage
  }
}
