<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue'

definePageMeta({
  layout: 'teacher',
  middleware: 'auth'
})

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'
const token = useCookie<string | null>('auth_token')
const { user } = useAuth()

// Canvas Refs
const containerRef = ref<HTMLDivElement | null>(null)
const bgCanvasRef = ref<HTMLCanvasElement | null>(null)
const drawCanvasRef = ref<HTMLCanvasElement | null>(null)

// Tools state
type ToolType = 'pen' | 'highlighter' | 'eraser' | 'line' | 'arrow' | 'rect' | 'circle' | 'text'
const currentTool = ref<ToolType>('pen')
const currentColor = ref('#059669') // Emerald MAN 11 default
const currentSize = ref(4)
const currentBg = ref<'white' | 'grid' | 'lines' | 'chalkboard'>('white')

// Preset Colors
const colorPalette = [
  { name: 'Emerald MAN 11', hex: '#059669' },
  { name: 'Charcoal Black', hex: '#1e293b' },
  { name: 'Madrasah Blue', hex: '#2563eb' },
  { name: 'Crimson Red', hex: '#e11d48' },
  { name: 'Amber Gold', hex: '#d97706' },
  { name: 'Purple Violet', hex: '#7c3aed' },
  { name: 'Chalk White', hex: '#f8fafc' },
]

// Preset Stroke Widths
const brushSizes = [
  { label: 'Halus', value: 2 },
  { label: 'Sedang', value: 4 },
  { label: 'Tebal', value: 8 },
  { label: 'Spidol', value: 14 },
  { label: 'Marker', value: 24 },
]

// Multi-Slide State
interface Slide {
  id: number
  title: string
  bg: 'white' | 'grid' | 'lines' | 'chalkboard'
  drawDataUrl: string | null
  undoStack: string[]
  redoStack: string[]
}

const slides = ref<Slide[]>([
  {
    id: 1,
    title: 'Slide 1',
    bg: 'white',
    drawDataUrl: null,
    undoStack: [],
    redoStack: []
  }
])
const activeSlideIndex = ref(0)
const currentSlide = computed(() => slides.value[activeSlideIndex.value])

// Fullscreen & UI state
const isFullscreen = ref(false)
const showShareModal = ref(false)
const isSharing = ref(false)
const shareSuccessMsg = ref('')

// Class & Meet selection for sharing
const courses = ref<any[]>([])
const meets = ref<any[]>([])
const selectedCourseId = ref('')
const selectedMeetId = ref('')
const shareTitle = ref('')

// In-canvas Text Tool State
const textInputVisible = ref(false)
const textInputX = ref(0)
const textInputY = ref(0)
const textInputValue = ref('')
const textInputRef = ref<HTMLInputElement | null>(null)

// Drawing State Machine
let isDrawing = false
let startX = 0
let startY = 0
let snapshotImageData: ImageData | null = null
let drawPoints: { x: number; y: number }[] = []

// Canvas dimension
const canvasWidth = ref(1600)
const canvasHeight = ref(900)

// Resize Canvas to fit container or standard 16:9 high-res
const resizeCanvas = () => {
  if (!containerRef.value || !bgCanvasRef.value || !drawCanvasRef.value) return

  const rect = containerRef.value.getBoundingClientRect()
  const dpr = window.devicePixelRatio || 1

  // Use container width/height
  const w = Math.max(rect.width, 800)
  const h = Math.max(rect.height, 550)

  canvasWidth.value = w
  canvasHeight.value = h

  const bgCanvas = bgCanvasRef.value
  const drawCanvas = drawCanvasRef.value

  // Save current drawing content before resizing
  let tempDrawing: string | null = null
  if (drawCanvas.width > 0 && drawCanvas.height > 0) {
    try {
      tempDrawing = drawCanvas.toDataURL()
    } catch (e) {}
  }

  // Adjust display size
  bgCanvas.style.width = `${w}px`
  bgCanvas.style.height = `${h}px`
  drawCanvas.style.width = `${w}px`
  drawCanvas.style.height = `${h}px`

  // Adjust internal canvas resolution with DPR
  bgCanvas.width = Math.floor(w * dpr)
  bgCanvas.height = Math.floor(h * dpr)
  drawCanvas.width = Math.floor(w * dpr)
  drawCanvas.height = Math.floor(h * dpr)

  const bgCtx = bgCanvas.getContext('2d')
  const drawCtx = drawCanvas.getContext('2d')

  if (bgCtx) bgCtx.scale(dpr, dpr)
  if (drawCtx) drawCtx.scale(dpr, dpr)

  renderBackground()

  // Restore drawing content if exists
  if (tempDrawing) {
    restoreDataUrl(tempDrawing)
  } else if (currentSlide.value.drawDataUrl) {
    restoreDataUrl(currentSlide.value.drawDataUrl)
  }
}

// Render background grid / lines / chalkboard
const renderBackground = () => {
  if (!bgCanvasRef.value) return
  const canvas = bgCanvasRef.value
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  const w = canvasWidth.value
  const h = canvasHeight.value
  const mode = currentSlide.value.bg

  ctx.save()

  if (mode === 'chalkboard') {
    // Blackboard dark chalkboard
    ctx.fillStyle = '#18241c'
    ctx.fillRect(0, 0, w, h)

    // Subtle dark texture dots
    ctx.fillStyle = 'rgba(255, 255, 255, 0.02)'
    for (let i = 0; i < w; i += 40) {
      for (let j = 0; j < h; j += 40) {
        ctx.fillRect(i, j, 1, 1)
      }
    }
  } else if (mode === 'grid') {
    // Mathematical grid
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, w, h)

    ctx.strokeStyle = '#e2e8f0'
    ctx.lineWidth = 1

    const gridSize = 24
    ctx.beginPath()
    for (let x = 0; x < w; x += gridSize) {
      ctx.moveTo(x, 0)
      ctx.lineTo(x, h)
    }
    for (let y = 0; y < h; y += gridSize) {
      ctx.moveTo(0, y)
      ctx.lineTo(w, y)
    }
    ctx.stroke()
  } else if (mode === 'lines') {
    // Notebook lined paper
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, w, h)

    // Red left margin line
    ctx.strokeStyle = '#fca5a5'
    ctx.lineWidth = 1.5
    ctx.beginPath()
    ctx.moveTo(70, 0)
    ctx.lineTo(70, h)
    ctx.stroke()

    // Blue horizontal lines
    ctx.strokeStyle = '#e2e8f0'
    ctx.lineWidth = 1
    const lineSpacing = 32
    ctx.beginPath()
    for (let y = 50; y < h; y += lineSpacing) {
      ctx.moveTo(0, y)
      ctx.lineTo(w, y)
    }
    ctx.stroke()
  } else {
    // Pure clean whiteboard
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, w, h)
  }

  ctx.restore()
}

// Set background mode
const setBackgroundMode = (mode: 'white' | 'grid' | 'lines' | 'chalkboard') => {
  currentSlide.value.bg = mode
  currentBg.value = mode

  // If switched to chalkboard and color is dark, auto switch to white chalk
  if (mode === 'chalkboard' && currentColor.value === '#1e293b') {
    currentColor.value = '#f8fafc'
  } else if (mode !== 'chalkboard' && currentColor.value === '#f8fafc') {
    currentColor.value = '#059669'
  }

  renderBackground()
}

// Convert event coordinates relative to canvas
const getPointerPos = (e: PointerEvent) => {
  if (!drawCanvasRef.value) return { x: 0, y: 0 }
  const rect = drawCanvasRef.value.getBoundingClientRect()
  return {
    x: e.clientX - rect.left,
    y: e.clientY - rect.top
  }
}

// Save Snapshot for Undo
const saveToUndo = () => {
  if (!drawCanvasRef.value) return
  try {
    const dataUrl = drawCanvasRef.value.toDataURL()
    currentSlide.value.drawDataUrl = dataUrl

    // Push snapshot
    currentSlide.value.undoStack.push(dataUrl)
    if (currentSlide.value.undoStack.length > 25) {
      currentSlide.value.undoStack.shift()
    }
    // Clear redo when new action is taken
    currentSlide.value.redoStack = []
  } catch (err) {}
}

// Undo
const handleUndo = () => {
  if (currentSlide.value.undoStack.length <= 1) return

  // Pop current state and push to redo
  const currentState = currentSlide.value.undoStack.pop()
  if (currentState) {
    currentSlide.value.redoStack.push(currentState)
  }

  // Restore previous
  const prevState = currentSlide.value.undoStack[currentSlide.value.undoStack.length - 1]
  if (prevState) {
    restoreDataUrl(prevState)
    currentSlide.value.drawDataUrl = prevState
  } else {
    clearCanvas(false)
  }
}

// Redo
const handleRedo = () => {
  if (currentSlide.value.redoStack.length === 0) return

  const nextState = currentSlide.value.redoStack.pop()
  if (nextState) {
    currentSlide.value.undoStack.push(nextState)
    restoreDataUrl(nextState)
    currentSlide.value.drawDataUrl = nextState
  }
}

// Restore DataURL onto draw canvas
const restoreDataUrl = (dataUrl: string) => {
  if (!drawCanvasRef.value) return
  const canvas = drawCanvasRef.value
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  const dpr = window.devicePixelRatio || 1
  const img = new Image()
  img.onload = () => {
    ctx.save()
    ctx.setTransform(1, 0, 0, 1, 0, 0)
    ctx.clearRect(0, 0, canvas.width, canvas.height)
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
    ctx.restore()
  }
  img.src = dataUrl
}

// Clear Drawing Canvas
const clearCanvas = (pushHistory = true) => {
  if (!drawCanvasRef.value) return
  const canvas = drawCanvasRef.value
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  ctx.save()
  ctx.setTransform(1, 0, 0, 1, 0, 0)
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  ctx.restore()

  currentSlide.value.drawDataUrl = null
  if (pushHistory) {
    saveToUndo()
  }
}

// Pointer Events Handlers
const onPointerDown = (e: PointerEvent) => {
  if (!drawCanvasRef.value) return
  const { x, y } = getPointerPos(e)

  // Text tool click
  if (currentTool.value === 'text') {
    commitText()
    textInputX.value = x
    textInputY.value = y
    textInputValue.value = ''
    textInputVisible.value = true
    nextTick(() => {
      textInputRef.value?.focus()
    })
    return
  }

  isDrawing = true
  startX = x
  startY = y
  drawPoints = [{ x, y }]

  const ctx = drawCanvasRef.value.getContext('2d')
  if (!ctx) return

  if (['line', 'arrow', 'rect', 'circle'].includes(currentTool.value)) {
    const dpr = window.devicePixelRatio || 1
    snapshotImageData = ctx.getImageData(0, 0, drawCanvasRef.value.width, drawCanvasRef.value.height)
  }
}

const onPointerMove = (e: PointerEvent) => {
  if (!isDrawing || !drawCanvasRef.value) return
  const { x, y } = getPointerPos(e)
  const ctx = drawCanvasRef.value.getContext('2d')
  if (!ctx) return

  ctx.save()

  // Configure stroke style
  ctx.lineWidth = currentSize.value
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.strokeStyle = currentColor.value

  if (currentTool.value === 'eraser') {
    // Destination-out to clear drawing layer without touching background
    ctx.globalCompositeOperation = 'destination-out'
    ctx.lineWidth = currentSize.value * 3.5

    ctx.beginPath()
    const lastPoint = drawPoints[drawPoints.length - 1] || { x, y }
    ctx.moveTo(lastPoint.x, lastPoint.y)
    ctx.lineTo(x, y)
    ctx.stroke()
    drawPoints.push({ x, y })
  } else if (currentTool.value === 'highlighter') {
    // Semi-transparent highlight
    ctx.globalCompositeOperation = 'source-over'
    ctx.globalAlpha = 0.35
    ctx.lineWidth = currentSize.value * 2.5

    ctx.beginPath()
    const lastPoint = drawPoints[drawPoints.length - 1] || { x, y }
    ctx.moveTo(lastPoint.x, lastPoint.y)
    ctx.lineTo(x, y)
    ctx.stroke()
    drawPoints.push({ x, y })
  } else if (currentTool.value === 'pen') {
    // Smooth freehand pen
    ctx.globalCompositeOperation = 'source-over'
    drawPoints.push({ x, y })

    if (drawPoints.length >= 3) {
      const p0 = drawPoints[drawPoints.length - 3]
      const p1 = drawPoints[drawPoints.length - 2]
      const p2 = drawPoints[drawPoints.length - 1]

      ctx.beginPath()
      ctx.moveTo((p0.x + p1.x) / 2, (p0.y + p1.y) / 2)
      ctx.quadraticCurveTo(p1.x, p1.y, (p1.x + p2.x) / 2, (p1.y + p2.y) / 2)
      ctx.stroke()
    } else {
      ctx.beginPath()
      ctx.moveTo(startX, startY)
      ctx.lineTo(x, y)
      ctx.stroke()
    }
  } else if (['line', 'arrow', 'rect', 'circle'].includes(currentTool.value)) {
    // Live preview geometric shapes using snapshot
    if (snapshotImageData) {
      ctx.putImageData(snapshotImageData, 0, 0)
    }

    ctx.globalCompositeOperation = 'source-over'

    if (currentTool.value === 'line') {
      ctx.beginPath()
      ctx.moveTo(startX, startY)
      ctx.lineTo(x, y)
      ctx.stroke()
    } else if (currentTool.value === 'arrow') {
      drawArrow(ctx, startX, startY, x, y)
    } else if (currentTool.value === 'rect') {
      ctx.strokeRect(startX, startY, x - startX, y - startY)
    } else if (currentTool.value === 'circle') {
      const radiusX = Math.abs(x - startX) / 2
      const radiusY = Math.abs(y - startY) / 2
      const centerX = startX + (x - startX) / 2
      const centerY = startY + (y - startY) / 2

      ctx.beginPath()
      ctx.ellipse(centerX, centerY, radiusX, radiusY, 0, 0, 2 * Math.PI)
      ctx.stroke()
    }
  }

  ctx.restore()
}

const onPointerUp = (e: PointerEvent) => {
  if (!isDrawing) return
  isDrawing = false
  drawPoints = []
  snapshotImageData = null
  saveToUndo()
}

// Draw Arrow helper
const drawArrow = (ctx: CanvasRenderingContext2D, fromX: number, fromY: number, toX: number, toY: number) => {
  const headLength = Math.max(14, currentSize.value * 3.5)
  const dx = toX - fromX
  const dy = toY - fromY
  const angle = Math.atan2(dy, dx)

  // Main line
  ctx.beginPath()
  ctx.moveTo(fromX, fromY)
  ctx.lineTo(toX, toY)
  ctx.stroke()

  // Arrowhead wings
  ctx.beginPath()
  ctx.moveTo(toX, toY)
  ctx.lineTo(toX - headLength * Math.cos(angle - Math.PI / 6), toY - headLength * Math.sin(angle - Math.PI / 6))
  ctx.lineTo(toX - headLength * Math.cos(angle + Math.PI / 6), toY - headLength * Math.sin(angle + Math.PI / 6))
  ctx.lineTo(toX, toY)
  ctx.fillStyle = currentColor.value
  ctx.fill()
}

// Text Tool Commit
const commitText = () => {
  if (!textInputVisible.value || !textInputValue.value.trim() || !drawCanvasRef.value) {
    textInputVisible.value = false
    textInputValue.value = ''
    return
  }

  const ctx = drawCanvasRef.value.getContext('2d')
  if (ctx) {
    ctx.save()
    ctx.fillStyle = currentColor.value
    ctx.font = `bold ${Math.max(16, currentSize.value * 4.5)}px sans-serif`
    ctx.textBaseline = 'top'
    ctx.fillText(textInputValue.value, textInputX.value, textInputY.value)
    ctx.restore()
    saveToUndo()
  }

  textInputVisible.value = false
  textInputValue.value = ''
}

// Multi-Slide Operations
const addSlide = () => {
  // Save current slide first
  if (drawCanvasRef.value) {
    currentSlide.value.drawDataUrl = drawCanvasRef.value.toDataURL()
  }

  const newId = slides.value.length + 1
  const newSlide: Slide = {
    id: newId,
    title: `Slide ${newId}`,
    bg: currentSlide.value.bg,
    drawDataUrl: null,
    undoStack: [],
    redoStack: []
  }

  slides.value.push(newSlide)
  activeSlideIndex.value = slides.value.length - 1

  clearCanvas(false)
  renderBackground()
  saveToUndo()
}

const switchSlide = (idx: number) => {
  if (idx < 0 || idx >= slides.value.length || idx === activeSlideIndex.value) return

  // Save current
  if (drawCanvasRef.value) {
    currentSlide.value.drawDataUrl = drawCanvasRef.value.toDataURL()
  }

  activeSlideIndex.value = idx
  currentBg.value = currentSlide.value.bg

  renderBackground()

  if (currentSlide.value.drawDataUrl) {
    restoreDataUrl(currentSlide.value.drawDataUrl)
  } else {
    clearCanvas(false)
  }
}

const prevSlide = () => switchSlide(activeSlideIndex.value - 1)
const nextSlide = () => switchSlide(activeSlideIndex.value + 1)

const deleteCurrentSlide = () => {
  if (slides.value.length <= 1) {
    clearCanvas()
    return
  }
  if (!confirm(`Hapus ${currentSlide.value.title}?`)) return

  slides.value.splice(activeSlideIndex.value, 1)
  if (activeSlideIndex.value >= slides.value.length) {
    activeSlideIndex.value = slides.value.length - 1
  }

  renderBackground()
  if (currentSlide.value.drawDataUrl) {
    restoreDataUrl(currentSlide.value.drawDataUrl)
  } else {
    clearCanvas(false)
  }
}

// Export Combined Canvas to High-Res Image Data URL
const getMergedCanvas = (): HTMLCanvasElement | null => {
  if (!bgCanvasRef.value || !drawCanvasRef.value) return null

  const merged = document.createElement('canvas')
  merged.width = bgCanvasRef.value.width
  merged.height = bgCanvasRef.value.height

  const ctx = merged.getContext('2d')
  if (!ctx) return null

  // Draw background layer
  ctx.drawImage(bgCanvasRef.value, 0, 0)
  // Draw drawing layer
  ctx.drawImage(drawCanvasRef.value, 0, 0)

  return merged
}

// Download Canvas as PNG
const downloadImage = () => {
  const merged = getMergedCanvas()
  if (!merged) return

  const link = document.createElement('a')
  link.download = `EduBoard-MAN11-${currentSlide.value.title}-${new Date().toISOString().substring(0, 10)}.png`
  link.href = merged.toDataURL('image/png')
  link.click()
}

// Fullscreen Toggle
const toggleFullscreen = () => {
  if (!document.fullscreenElement) {
    containerRef.value?.requestFullscreen().then(() => {
      isFullscreen.value = true
      setTimeout(resizeCanvas, 150)
    }).catch(() => {})
  } else {
    document.exitFullscreen().then(() => {
      isFullscreen.value = false
      setTimeout(resizeCanvas, 150)
    }).catch(() => {})
  }
}

// Fetch Courses and Meets for "Simpan & Bagikan ke Kelas"
const openShareModal = async () => {
  showShareModal.value = true
  shareSuccessMsg.value = ''
  shareTitle.value = `Catatan EduBoard: ${currentSlide.value.title} (${new Date().toLocaleDateString('id-ID')})`

  try {
    const res: any = await $fetch(`${apiBase}/courses?my_courses=true&all=true`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    courses.value = res.data || res || []
    if (courses.value.length > 0) {
      selectedCourseId.value = String(courses.value[0].id)
      await loadMeetsForCourse(courses.value[0].id)
    }
  } catch (err) {
    console.error('Fetch courses error:', err)
  }
}

const loadMeetsForCourse = async (courseId: any) => {
  try {
    const res: any = await $fetch(`${apiBase}/meets?course_id=${courseId}&paginate=false`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    meets.value = res.data || res || []
    if (meets.value.length > 0) {
      selectedMeetId.value = String(meets.value[0].id)
    } else {
      selectedMeetId.value = ''
    }
  } catch (err) {
    meets.value = []
  }
}

// Submit Share to Meet
const handleShareToClass = async () => {
  if (!selectedMeetId.value) {
    alert('Silakan pilih sesi pertemuan kelas terlebih dahulu.')
    return
  }

  const merged = getMergedCanvas()
  if (!merged) return

  isSharing.value = true
  shareSuccessMsg.value = ''

  try {
    merged.toBlob(async (blob) => {
      if (!blob) {
        alert('Gagal mengekspor gambar kanvas.')
        isSharing.value = false
        return
      }

      const formData = new FormData()
      formData.append('meet_id', selectedMeetId.value)
      formData.append('title', shareTitle.value || 'Catatan EduBoard')
      formData.append('type', 'file')
      formData.append('content', `Coretan papan tulis digital EduBoard untuk sesi pembelajaran ${currentSlide.value.title}.`)
      formData.append('file', blob, `EduBoard-${Date.now()}.png`)

      try {
        await $fetch(`${apiBase}/materials`, {
          method: 'POST',
          headers: { Authorization: `Bearer ${token.value}` },
          body: formData
        })

        shareSuccessMsg.value = 'Berhasil! Catatan EduBoard telah terunggah ke ruang materi pertemuan kelas siswa.'
        setTimeout(() => {
          showShareModal.value = false
          shareSuccessMsg.value = ''
        }, 2500)
      } catch (err: any) {
        alert(err?.data?.message || 'Gagal mengunggah materi ke kelas.')
      } finally {
        isSharing.value = false
      }
    }, 'image/png')
  } catch (err) {
    isSharing.value = false
  }
}

// Keyboard shortcuts (Z: undo, Y: redo, P: pen, E: eraser, H: highlighter)
const onKeyDown = (e: KeyboardEvent) => {
  if (textInputVisible.value) {
    if (e.key === 'Enter') commitText()
    if (e.key === 'Escape') textInputVisible.value = false
    return
  }

  if (e.ctrlKey || e.metaKey) {
    if (e.key === 'z') {
      e.preventDefault()
      handleUndo()
    } else if (e.key === 'y') {
      e.preventDefault()
      handleRedo()
    }
  } else {
    if (e.key === 'p' || e.key === 'P') currentTool.value = 'pen'
    else if (e.key === 'e' || e.key === 'E') currentTool.value = 'eraser'
    else if (e.key === 'h' || e.key === 'H') currentTool.value = 'highlighter'
    else if (e.key === 't' || e.key === 'T') currentTool.value = 'text'
  }
}

onMounted(() => {
  nextTick(() => {
    resizeCanvas()
    saveToUndo()
  })

  window.addEventListener('resize', resizeCanvas)
  window.addEventListener('keydown', onKeyDown)

  document.addEventListener('fullscreenchange', () => {
    isFullscreen.value = !!document.fullscreenElement
    setTimeout(resizeCanvas, 150)
  })
})

onUnmounted(() => {
  window.removeEventListener('resize', resizeCanvas)
  window.removeEventListener('keydown', onKeyDown)
})
</script>

<template>
  <main class="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans select-none">
    <!-- Top Bar Navigation (Hidden in Fullscreen) -->
    <header v-if="!isFullscreen" class="bg-slate-950/90 backdrop-blur-md border-b border-slate-800 px-4 py-2.5 flex items-center justify-between z-20">
      <div class="flex items-center gap-3">
        <NuxtLink
          to="/teacher/dashboard"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-xs font-bold text-slate-300 transition"
        >
          ← Dashboard
        </NuxtLink>
        <div class="h-4 w-px bg-slate-800"></div>
        <div class="flex items-center gap-2">
          <div class="h-7 w-7 rounded-lg bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
            </svg>
          </div>
          <div>
            <h1 class="text-sm font-extrabold text-white tracking-tight flex items-center gap-2">
              EduBoard MAN 11
              <span class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                Papan Tulis Digital
              </span>
            </h1>
          </div>
        </div>
      </div>

      <!-- Slide Management in Header -->
      <div class="flex items-center gap-1.5 bg-slate-900 px-2 py-1 rounded-2xl border border-slate-800">
        <button
          @click="prevSlide"
          :disabled="activeSlideIndex === 0"
          class="p-1.5 rounded-xl hover:bg-slate-800 text-slate-400 hover:text-white disabled:opacity-30 transition"
          title="Slide Sebelumnya"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>

        <span class="text-xs font-bold text-slate-200 px-2">
          {{ currentSlide.title }} <span class="text-slate-500 font-normal">/ {{ slides.length }}</span>
        </span>

        <button
          @click="nextSlide"
          :disabled="activeSlideIndex === slides.length - 1"
          class="p-1.5 rounded-xl hover:bg-slate-800 text-slate-400 hover:text-white disabled:opacity-30 transition"
          title="Slide Berikutnya"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <button
          @click="addSlide"
          class="ml-1 px-2.5 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition flex items-center gap-1"
          title="Tambah Slide Baru"
        >
          + Slide
        </button>

        <button
          v-if="slides.length > 1"
          @click="deleteCurrentSlide"
          class="p-1.5 rounded-xl hover:bg-rose-500/20 text-rose-400 transition"
          title="Hapus Slide Ini"
        >
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <button
          @click="openShareModal"
          class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
          </svg>
          Bagikan ke Kelas
        </button>

        <button
          @click="downloadImage"
          class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold transition flex items-center gap-1"
          title="Unduh Gambar PNG"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Unduh PNG
        </button>

        <button
          @click="toggleFullscreen"
          class="p-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold transition"
          :title="isFullscreen ? 'Keluar Layar Penuh' : 'Mode Layar Penuh'"
        >
          <svg v-if="!isFullscreen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
          </svg>
          <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </header>

    <!-- Canvas Container Workspace -->
    <div
      ref="containerRef"
      class="relative flex-1 w-full h-full overflow-hidden bg-slate-900 cursor-crosshair"
    >
      <!-- Layer 0: Background Canvas -->
      <canvas
        ref="bgCanvasRef"
        class="absolute inset-0 pointer-events-none"
      ></canvas>

      <!-- Layer 1: Interactive Drawing Canvas -->
      <canvas
        ref="drawCanvasRef"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
        class="absolute inset-0 touch-none"
      ></canvas>

      <!-- In-Place Text Input Overlay -->
      <div
        v-if="textInputVisible"
        :style="{ left: `${textInputX}px`, top: `${textInputY}px` }"
        class="absolute z-30"
      >
        <input
          ref="textInputRef"
          v-model="textInputValue"
          @blur="commitText"
          type="text"
          placeholder="Ketik catatan..."
          class="px-2 py-1 bg-white text-slate-900 border-2 border-emerald-500 rounded-lg text-sm font-bold shadow-lg focus:outline-none min-w-[200px]"
        />
      </div>

      <!-- FLOATING TOOLBAR -->
      <div class="absolute bottom-5 left-1/2 -translate-x-1/2 z-30 flex items-center gap-1.5 p-2 bg-slate-950/85 backdrop-blur-xl border border-slate-700/80 rounded-2xl shadow-2xl">
        <!-- Tools Palette -->
        <div class="flex items-center gap-1 pr-2 border-r border-slate-800">
          <!-- Pen -->
          <button
            @click="currentTool = 'pen'"
            class="p-2 rounded-xl transition"
            :class="currentTool === 'pen' ? 'bg-emerald-600 text-white shadow-md' : 'text-slate-400 hover:bg-slate-800 hover:text-white'"
            title="Spidol / Pen (P)"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
            </svg>
          </button>

          <!-- Highlighter -->
          <button
            @click="currentTool = 'highlighter'"
            class="p-2 rounded-xl transition"
            :class="currentTool === 'highlighter' ? 'bg-emerald-600 text-white shadow-md' : 'text-slate-400 hover:bg-slate-800 hover:text-white'"
            title="Highlighter / Stabilo (H)"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
          </button>

          <!-- Eraser -->
          <button
            @click="currentTool = 'eraser'"
            class="p-2 rounded-xl transition"
            :class="currentTool === 'eraser' ? 'bg-emerald-600 text-white shadow-md' : 'text-slate-400 hover:bg-slate-800 hover:text-white'"
            title="Penghapus Coretan (E)"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>

          <!-- Shapes: Line -->
          <button
            @click="currentTool = 'line'"
            class="p-2 rounded-xl transition"
            :class="currentTool === 'line' ? 'bg-emerald-600 text-white shadow-md' : 'text-slate-400 hover:bg-slate-800 hover:text-white'"
            title="Garis Lurus"
          >
            <span class="block w-4 h-0.5 bg-current rotate-45 my-2"></span>
          </button>

          <!-- Shapes: Arrow -->
          <button
            @click="currentTool = 'arrow'"
            class="p-2 rounded-xl transition"
            :class="currentTool === 'arrow' ? 'bg-emerald-600 text-white shadow-md' : 'text-slate-400 hover:bg-slate-800 hover:text-white'"
            title="Panah Penunjuk"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </button>

          <!-- Shapes: Rectangle -->
          <button
            @click="currentTool = 'rect'"
            class="p-2 rounded-xl transition"
            :class="currentTool === 'rect' ? 'bg-emerald-600 text-white shadow-md' : 'text-slate-400 hover:bg-slate-800 hover:text-white'"
            title="Kotak Geometri"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <rect x="4" y="4" width="16" height="16" rx="2" stroke-width="2" />
            </svg>
          </button>

          <!-- Shapes: Circle -->
          <button
            @click="currentTool = 'circle'"
            class="p-2 rounded-xl transition"
            :class="currentTool === 'circle' ? 'bg-emerald-600 text-white shadow-md' : 'text-slate-400 hover:bg-slate-800 hover:text-white'"
            title="Lingkaran"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <circle cx="12" cy="12" r="8" stroke-width="2" />
            </svg>
          </button>

          <!-- Text Tool -->
          <button
            @click="currentTool = 'text'"
            class="p-2 rounded-xl transition"
            :class="currentTool === 'text' ? 'bg-emerald-600 text-white shadow-md' : 'text-slate-400 hover:bg-slate-800 hover:text-white'"
            title="Tulis Teks Catatan (T)"
          >
            <span class="text-base font-black">T</span>
          </button>
        </div>

        <!-- Color Palette Picker -->
        <div class="flex items-center gap-1.5 px-2 border-r border-slate-800">
          <button
            v-for="color in colorPalette"
            :key="color.hex"
            @click="currentColor = color.hex"
            class="h-6 w-6 rounded-full border-2 transition transform hover:scale-110"
            :style="{ backgroundColor: color.hex }"
            :class="currentColor === color.hex ? 'border-white scale-110 ring-2 ring-emerald-500/50' : 'border-slate-700'"
            :title="color.name"
          ></button>

          <!-- Custom HTML Color Input -->
          <label class="relative h-6 w-6 rounded-full overflow-hidden border border-slate-700 cursor-pointer flex items-center justify-center bg-gradient-to-tr from-pink-500 via-amber-500 to-sky-500" title="Pilih Warna Lain">
            <input type="color" v-model="currentColor" class="opacity-0 absolute inset-0 cursor-pointer" />
          </label>
        </div>

        <!-- Stroke Width Presets -->
        <div class="flex items-center gap-1 px-2 border-r border-slate-800">
          <button
            v-for="size in brushSizes"
            :key="size.value"
            @click="currentSize = size.value"
            class="p-1.5 rounded-lg flex items-center justify-center transition"
            :class="currentSize === size.value ? 'bg-slate-800 text-emerald-400' : 'text-slate-500 hover:text-slate-300'"
            :title="`Ketebalan: ${size.label} (${size.value}px)`"
          >
            <span
              class="rounded-full bg-current"
              :style="{ width: `${Math.max(4, size.value * 0.8)}px`, height: `${Math.max(4, size.value * 0.8)}px` }"
            ></span>
          </button>
        </div>

        <!-- Canvas Background Selector -->
        <div class="flex items-center gap-1 px-2 border-r border-slate-800">
          <button
            @click="setBackgroundMode('white')"
            class="px-2 py-1 rounded-lg text-[10px] font-bold transition"
            :class="currentSlide.bg === 'white' ? 'bg-white text-slate-900' : 'text-slate-400 hover:bg-slate-800'"
            title="Papan Putih Bersih"
          >
            Putih
          </button>
          <button
            @click="setBackgroundMode('grid')"
            class="px-2 py-1 rounded-lg text-[10px] font-bold transition"
            :class="currentSlide.bg === 'grid' ? 'bg-slate-200 text-slate-900' : 'text-slate-400 hover:bg-slate-800'"
            title="Kertas Kotak Matematika"
          >
            Kotak
          </button>
          <button
            @click="setBackgroundMode('lines')"
            class="px-2 py-1 rounded-lg text-[10px] font-bold transition"
            :class="currentSlide.bg === 'lines' ? 'bg-slate-200 text-slate-900' : 'text-slate-400 hover:bg-slate-800'"
            title="Kertas Garis Bergaris"
          >
            Garis
          </button>
          <button
            @click="setBackgroundMode('chalkboard')"
            class="px-2 py-1 rounded-lg text-[10px] font-bold transition"
            :class="currentSlide.bg === 'chalkboard' ? 'bg-emerald-950 text-emerald-300 border border-emerald-700' : 'text-slate-400 hover:bg-slate-800'"
            title="Papan Tulis Hitam (Chalkboard)"
          >
            Hitam
          </button>
        </div>

        <!-- History: Undo, Redo, Clear -->
        <div class="flex items-center gap-1 pl-1">
          <button
            @click="handleUndo"
            :disabled="currentSlide.undoStack.length <= 1"
            class="p-2 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white disabled:opacity-30 transition"
            title="Undo (Ctrl+Z)"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
            </svg>
          </button>

          <button
            @click="handleRedo"
            :disabled="currentSlide.redoStack.length === 0"
            class="p-2 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white disabled:opacity-30 transition"
            title="Redo (Ctrl+Y)"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2m18-10l-6 6m6-6l-6-6" />
            </svg>
          </button>

          <button
            @click="clearCanvas"
            class="p-2 rounded-xl text-rose-400 hover:bg-rose-500/20 transition"
            title="Bersihkan Seluruh Kanvas"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL SHARE TO CLASS (SIMPAN & BAGIKAN KE PERTEMUAN KELAS) -->
    <div v-if="showShareModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-2xl space-y-4 text-slate-800 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Integrasi LMS</span>
            <h3 class="text-lg font-extrabold text-slate-900 mt-0.5">Bagikan Coretan ke Kelas</h3>
          </div>
          <button @click="showShareModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div v-if="shareSuccessMsg" class="p-3 bg-emerald-50 text-emerald-800 rounded-2xl text-xs font-bold border border-emerald-200">
          {{ shareSuccessMsg }}
        </div>

        <form v-else @submit.prevent="handleShareToClass" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Pilih Kelas Pengampuan *</label>
            <select
              v-model="selectedCourseId"
              @change="loadMeetsForCourse(selectedCourseId)"
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              required
            >
              <option v-for="c in courses" :key="c.id" :value="c.id">
                {{ c.subject?.name }} - {{ c.class?.class_name?.name || c.class?.className?.name || 'Kelas' }}
              </option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Pilih Sesi Pertemuan *</label>
            <select
              v-model="selectedMeetId"
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              required
            >
              <option value="" disabled>-- Pilih Sesi Pertemuan --</option>
              <option v-for="m in meets" :key="m.id" :value="m.id">
                Pertemuan {{ m.meeting_number }}: {{ m.title }}
              </option>
            </select>
            <p v-if="meets.length === 0" class="text-[11px] text-amber-600 mt-1">
              Belum ada pertemuan di kelas ini. Buat pertemuan terlebih dahulu di menu Kelas Saya.
            </p>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Judul Materi Catatan</label>
            <input
              v-model="shareTitle"
              type="text"
              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              required
            />
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showShareModal = false"
              class="px-4 py-2 border rounded-xl font-bold text-slate-600 hover:bg-slate-50"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSharing || !selectedMeetId"
              class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 disabled:opacity-50"
            >
              <svg v-if="isSharing" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ isSharing ? 'Mengunggah...' : 'Kirim Catatan ke Pertemuan' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </main>
</template>
