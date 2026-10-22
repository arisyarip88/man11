export interface CompressedImageResult {
  blob: Blob
  width: number
  height: number
  originalSize: number
  compressedSize: number
  mimeType: string
}

export const useImageCompressor = () => {
  /**
   * Mengompresi dan me-resize gambar di browser menggunakan Canvas API sebelum diunggah.
   * Mengurangi beban upload dari 3-6 MB menjadi 40-100 KB WebP tanpa kehilangan ketajaman diagram.
   */
  const compressImage = async (
    file: File,
    maxWidth = 1200,
    maxHeight = 800,
    quality = 0.82
  ): Promise<CompressedImageResult> => {
    return new Promise((resolve, reject) => {
      // Jika SVG, tidak perlu kompresi kanvas (pertahankan vektor murni)
      if (file.type === 'image/svg+xml') {
        return resolve({
          blob: file,
          width: 0,
          height: 0,
          originalSize: file.size,
          compressedSize: file.size,
          mimeType: 'image/svg+xml'
        })
      }

      if (!file.type.startsWith('image/')) {
        return reject(new Error('Berkas yang dipilih bukan gambar'))
      }

      const reader = new FileReader()
      reader.onload = (e) => {
        const img = new Image()
        img.onload = () => {
          let { width, height } = img

          // Batasi resolusi maksimal untuk diagram soal pendidikan (max 1200x800)
          if (width > maxWidth || height > maxHeight) {
            const ratio = Math.min(maxWidth / width, maxHeight / height)
            width = Math.round(width * ratio)
            height = Math.round(height * ratio)
          }

          const canvas = document.createElement('canvas')
          canvas.width = width
          canvas.height = height
          const ctx = canvas.getContext('2d')

          if (!ctx) {
            return reject(new Error('Canvas 2D context tidak tersedia di browser'))
          }

          // Anti-aliasing tingkat tinggi
          ctx.imageSmoothingEnabled = true
          ctx.imageSmoothingQuality = 'high'

          // Gambar ulang ke canvas dengan dimensi proporsional
          ctx.drawImage(img, 0, 0, width, height)

          // Coba ekspor ke image/webp, fallback ke image/jpeg jika browser tidak mendukung
          const targetMime = 'image/webp'

          canvas.toBlob(
            (blob) => {
              if (blob) {
                resolve({
                  blob,
                  width,
                  height,
                  originalSize: file.size,
                  compressedSize: blob.size,
                  mimeType: targetMime
                })
              } else {
                // Fallback ke JPEG jika WebP gagal
                canvas.toBlob(
                  (fallbackBlob) => {
                    if (fallbackBlob) {
                      resolve({
                        blob: fallbackBlob,
                        width,
                        height,
                        originalSize: file.size,
                        compressedSize: fallbackBlob.size,
                        mimeType: 'image/jpeg'
                      })
                    } else {
                      reject(new Error('Gagal menghasilkan blob gambar terkompresi'))
                    }
                  },
                  'image/jpeg',
                  quality
                )
              }
            },
            targetMime,
            quality
          )
        }

        img.onerror = () => reject(new Error('Gagal memuat gambar ke memori peramban'))
        img.src = e.target?.result as string
      }

      reader.onerror = () => reject(new Error('Gagal membaca file gambar'))
      reader.readAsDataURL(file)
    })
  }

  return {
    compressImage
  }
}
