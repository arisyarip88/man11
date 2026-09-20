<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit,
    Link.configure({
      openOnClick: false,
      HTMLAttributes: {
        class: 'text-indigo-600 underline font-semibold',
        target: '_blank'
      }
    })
  ],
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  }
})

// Sync nilai saat prop dari luar berubah (misal saat Edit Modal dibuka)
watch(() => props.modelValue, (value) => {
  const isSame = editor.value?.getHTML() === value
  if (!isSame && editor.value) {
    editor.value.commands.setContent(value, false)
  }
})

onBeforeUnmount(() => {
  editor.value?.destroy()
})

// Fungsi Tambah Link
const setLink = () => {
  const previousUrl = editor.value?.getAttributes('link').href
  const url = window.prompt('Masukkan URL / Link:', previousUrl)

  if (url === null) return
  if (url === '') {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }

  editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}
</script>

<template>
  <div class="border border-slate-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 bg-white">
    <!-- Toolbar Editor -->
    <div v-if="editor" class="flex flex-wrap items-center gap-1 p-2 bg-slate-50 border-b border-slate-200 text-xs">
      <!-- Bold -->
      <button 
        type="button" 
        @click="editor.chain().focus().toggleBold().run()"
        :class="{ 'bg-indigo-100 text-indigo-600 font-bold': editor.isActive('bold') }"
        class="px-2 py-1 rounded hover:bg-slate-200 transition"
      >
        B
      </button>

      <!-- Italic -->
      <button 
        type="button" 
        @click="editor.chain().focus().toggleItalic().run()"
        :class="{ 'bg-indigo-100 text-indigo-600 italic': editor.isActive('italic') }"
        class="px-2 py-1 rounded hover:bg-slate-200 transition"
      >
        I
      </button>

      <!-- Bullet List -->
      <button 
        type="button" 
        @click="editor.chain().focus().toggleBulletList().run()"
        :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive('bulletList') }"
        class="px-2 py-1 rounded hover:bg-slate-200 transition"
      >
        • List
      </button>

      <!-- Insert Link -->
      <button 
        type="button" 
        @click="setLink"
        :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive('link') }"
        class="px-2 py-1 rounded hover:bg-slate-200 transition font-medium flex items-center gap-1"
      >
        🔗 Link
      </button>
    </div>

    <!-- Area Input Teks Editor -->
    <EditorContent :editor="editor" class="p-3 min-h-[100px] max-h-[200px] overflow-y-auto text-sm focus:outline-none prose prose-sm max-w-none" />
  </div>
</template>

<style>
/* Styling default untuk area editor */
.ProseMirror {
  outline: none !important;
}
.ProseMirror p {
  margin: 0.25rem 0;
}
.ProseMirror ul {
  list-style-type: disc;
  padding-left: 1.25rem;
}
</style>