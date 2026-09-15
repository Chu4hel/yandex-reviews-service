export interface AdaptiveTruncationResult {
  isLong: boolean
  preview: string
}

/**
 * Адаптивное сжатие длинного текста отзыва или ответа организации:
 * 1. Порог срабатывания: тексты короче 500 символов (и до 5 строк) не скрываются вовсе.
 * 2. Для длинных текстов ищется естественная граница: конец абзаца (\n) или конец предложения (. ! ?).
 * 3. Если после точки остается короткий остаток (< 80 символов), он не обрезается, а дописывается целиком.
 * 4. Если предложений в диапазоне нет — срез выполняется строго по границе целого слова.
 *
 * @param text Исходный текст для отображения
 * @returns Результат со статусом необходимости сжатия и текстом превью
 */
export const computeAdaptiveTruncation = (text: string | null | undefined): AdaptiveTruncationResult => {
  if (!text) {
    return { isLong: false, preview: '' }
  }

  const trimmed = text.trim()
  const totalLength = trimmed.length
  const lines = trimmed.split('\n')

  const TRIGGER_LENGTH = 500
  const MAX_VISIBLE_LINES = 5
  const MIN_REMAINDER = 60

  // Короткие и средние тексты показываем целиком без сворачивания
  if (totalLength <= TRIGGER_LENGTH && lines.length <= MAX_VISIBLE_LINES) {
    return { isLong: false, preview: trimmed }
  }

  // Если текст до 500 символов, но разбит на множество мелких строк (> 5)
  if (totalLength <= TRIGGER_LENGTH && lines.length > MAX_VISIBLE_LINES) {
    const firstLines = lines.slice(0, 4).join('\n').trimEnd()
    const remainder = lines.slice(4).join('\n').trim()
    if (remainder.length < MIN_REMAINDER) {
      return { isLong: false, preview: trimmed }
    }
    return {
      isLong: true,
      preview: firstLines + '...',
    }
  }

  const SEARCH_START = 280
  const SEARCH_END = 420

  // 1. Поиск границы абзаца / переноса строки (\n) в диапазоне [260, 420]
  const paragraphIndex = trimmed.lastIndexOf('\n', SEARCH_END)
  if (paragraphIndex >= 260) {
    const remainder = trimmed.slice(paragraphIndex + 1).trim()
    if (remainder.length < MIN_REMAINDER) {
      return { isLong: false, preview: trimmed }
    }
    return {
      isLong: true,
      preview: trimmed.slice(0, paragraphIndex).trimEnd() + '...',
    }
  }

  // 2. Поиск завершения предложения (. ! ? ...) в диапазоне [SEARCH_START, SEARCH_END]
  const searchSlice = trimmed.slice(0, SEARCH_END)
  const sentenceMatches = Array.from(searchSlice.matchAll(/([.!?]+)([\s\n"»]|$)/g))

  let bestSentenceEnd = -1
  for (let i = sentenceMatches.length - 1; i >= 0; i--) {
    const match = sentenceMatches[i]
    if (match.index !== undefined) {
      const punctEnd = match.index + match[1].length
      if (punctEnd >= SEARCH_START) {
        bestSentenceEnd = punctEnd
        break
      }
    }
  }

  if (bestSentenceEnd > 0) {
    const remainder = trimmed.slice(bestSentenceEnd).trim()
    // Если после предложения остался совсем небольшой хвост — дописываем до конца
    if (remainder.length < MIN_REMAINDER) {
      return { isLong: false, preview: trimmed }
    }
    return {
      isLong: true,
      preview: trimmed.slice(0, bestSentenceEnd).trimEnd() + '...',
    }
  }

  // 3. Срез около 380 символов по границе целого слова
  const TARGET_LENGTH = 380
  const rawSlice = trimmed.slice(0, TARGET_LENGTH)
  const lastSpace = rawSlice.lastIndexOf(' ')
  const safeEnd = lastSpace > 280 ? lastSpace : TARGET_LENGTH

  const remainder = trimmed.slice(safeEnd).trim()
  if (remainder.length < MIN_REMAINDER) {
    return { isLong: false, preview: trimmed }
  }

  const cleanSlice = trimmed.slice(0, safeEnd).replace(/[,;:\-\s]+$/, '')
  return {
    isLong: true,
    preview: cleanSlice + '...',
  }
}
