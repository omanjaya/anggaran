/**
 * Composable for formatting values (currency, date, number, etc.)
 */
export function useFormat() {
  /**
   * Format number as Indonesian Rupiah
   */
  const formatCurrency = (value: number | null | undefined, options?: { showSymbol?: boolean }) => {
    if (value == null) return '-'
    const formatted = new Intl.NumberFormat('id-ID', {
      style: options?.showSymbol !== false ? 'currency' : 'decimal',
      currency: 'IDR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(value)
    return formatted
  }

  /**
   * Format number with thousand separator
   */
  const formatNumber = (value: number | null | undefined, decimals = 0) => {
    if (value == null) return '-'
    return new Intl.NumberFormat('id-ID', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }).format(value)
  }

  /**
   * Format percentage
   */
  const formatPercent = (value: number | null | undefined, decimals = 1) => {
    if (value == null) return '-'
    return `${value >= 0 ? '' : ''}${value.toFixed(decimals)}%`
  }

  /**
   * Format date to Indonesian locale
   */
  const formatDate = (
    date: string | Date | null | undefined,
    options?: {
      format?: 'short' | 'long' | 'full' | 'datetime'
    }
  ) => {
    if (!date) return '-'
    const d = typeof date === 'string' ? new Date(date) : date

    const formatOptions: Record<string, Intl.DateTimeFormatOptions> = {
      short: { day: '2-digit', month: '2-digit', year: 'numeric' },
      long: { day: 'numeric', month: 'long', year: 'numeric' },
      full: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
      datetime: {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      },
    }

    return d.toLocaleDateString('id-ID', formatOptions[options?.format || 'short'])
  }

  /**
   * Format month number to month name
   */
  const formatMonth = (month: number) => {
    const months = [
      '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ]
    return months[month] || '-'
  }

  /**
   * Format file size
   */
  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
  }

  return {
    formatCurrency,
    formatNumber,
    formatPercent,
    formatDate,
    formatMonth,
    formatFileSize,
  }
}
