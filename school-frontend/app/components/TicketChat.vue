<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'

interface User {
  id: number
  name: string
  username?: string
}

interface Message {
  id: number
  ticket_id: number
  user_id: number
  message: string
  created_at: string
  user: User
}

const props = defineProps<{
  ticketId: string | number
}>()

const config = useRuntimeConfig()
const apiBase = config.public.apiBase || 'http://localhost:8000/api'

// Sesuaikan composable Auth dengan yang Anda gunakan di aplikasi
const { token, user } = useAuth() 

const messages = ref<Message[]>([])
const newMessage = ref('')
const loading = ref(false)
const sending = ref(false)
const chatContainer = ref<HTMLElement | null>(null)

// Helper Header dengan Token
const getAuthHeaders = () => {
  const authToken = unref(token)
  return {
    'Accept': 'application/json',
    'Authorization': authToken ? `Bearer ${authToken}` : ''
  }
}

// Auto Scroll ke Bawah
const scrollToBottom = async () => {
  await nextTick()
  if (chatContainer.value) {
    chatContainer.value.scrollTop = chatContainer.value.scrollHeight
  }
}

// Get Data Pesan
const fetchMessages = async () => {
  loading.value = true
  try {
    const res: any = await $fetch(`${apiBase}/tickets/${props.ticketId}/messages`, {
      headers: getAuthHeaders()
    })
    messages.value = res.data || []
    scrollToBottom()
  } catch (err: any) {
    console.error('Gagal mengambil pesan:', err)
  } finally {
    loading.value = false
  }
}

// Send Pesan
const sendMessage = async () => {
  if (!newMessage.value.trim() || sending.value) return

  sending.value = true
  const textPayload = newMessage.value
  newMessage.value = ''

  try {
    const res: any = await $fetch(`${apiBase}/tickets/${props.ticketId}/messages`, {
      method: 'POST',
      headers: getAuthHeaders(),
      body: { message: textPayload }
    })

    messages.value.push(res.data)
    scrollToBottom()
  } catch (err: any) {
    alert(err.data?.message || 'Gagal mengirim pesan. Pastikan Anda sudah login.')
    newMessage.value = textPayload // kembalikan teks jika gagal
  } finally {
    sending.value = false
  }
}

const formatTime = (timeStr: string) => {
  if (!timeStr) return ''
  return new Date(timeStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

onMounted(() => {
  fetchMessages()
})
</script>

<template>
  <div class="flex flex-col h-[550px] bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <!-- Header Chat -->
    <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
      <div>
        <h3 class="text-sm font-bold text-slate-800">Diskusi Ticket</h3>
        <p class="text-xs text-slate-400">#TICKET-{{ props.ticketId }}</p>
      </div>
      <button 
        @click="fetchMessages" 
        class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition"
        title="Refresh Pesan"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
      </button>
    </div>

    <!-- Area Chat Messages -->
    <div ref="chatContainer" class="flex-1 p-4 overflow-y-auto space-y-4 bg-slate-50/30">
      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center h-full text-xs text-slate-400">
        Memuat diskusi...
      </div>

      <!-- Empty State -->
      <div v-else-if="messages.length === 0" class="flex flex-col items-center justify-center h-full text-xs text-slate-400 space-y-1">
        <span>Belum ada pesan pada ticket ini.</span>
        <span>Kirim pesan pertama Anda di bawah ini.</span>
      </div>

      <!-- Chat Bubble List -->
      <template v-else>
        <div 
          v-for="msg in messages" 
          :key="msg.id" 
          class="flex flex-col"
          :class="msg.user_id === user?.id ? 'items-end' : 'items-start'"
        >
          <!-- Identitas Pengirim (Nama & @username) -->
          <span class="text-[10px] font-medium text-slate-400 mb-1 px-1">
            {{ msg.user_id === user?.id ? 'Anda' : msg.user?.name }}
            <template v-if="msg.user?.username">(@{{ msg.user.username }})</template>
          </span>

          <!-- Bubble Pesan -->
          <div 
            class="max-w-[75%] rounded-2xl px-4 py-2.5 text-sm shadow-sm space-y-1"
            :class="msg.user_id === user?.id 
              ? 'bg-indigo-600 text-white rounded-br-none' 
              : 'bg-white text-slate-800 border border-slate-100 rounded-bl-none'"
          >
            <p class="whitespace-pre-wrap leading-relaxed">{{ msg.message }}</p>
            <div 
              class="text-[10px] text-right"
              :class="msg.user_id === user?.id ? 'text-indigo-200' : 'text-slate-400'"
            >
              {{ formatTime(msg.created_at) }}
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Input Kirim Pesan -->
    <form @submit.prevent="sendMessage" class="p-3 bg-white border-t border-slate-100 flex items-center gap-2">
      <input 
        v-model="newMessage"
        type="text" 
        placeholder="Ketik pesan balasan..."
        class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition"
      />
      <button 
        type="submit" 
        :disabled="!newMessage.trim() || sending"
        class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition flex items-center gap-1.5 shadow-sm shadow-indigo-200"
      >
        <span>{{ sending ? 'Mengirim...' : 'Kirim' }}</span>
        <svg v-if="!sending" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
        </svg>
      </button>
    </form>
  </div>
</template>