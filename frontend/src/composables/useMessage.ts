import { useMessage as useNaiveMessage, useDialog, useNotification } from 'naive-ui'

export function useMessage() {
  const message = useNaiveMessage()
  const dialog = useDialog()
  const notification = useNotification()

  return {
    // Quick messages (toast style)
    success: (content: string) => message.success(content),
    error: (content: string) => message.error(content),
    warning: (content: string) => message.warning(content),
    info: (content: string) => message.info(content),
    loading: (content: string) => message.loading(content),

    // Dialogs - can accept string or options object
    confirm: (
      messageOrOptions:
        | string
        | {
            title: string
            content: string
            positiveText?: string
            negativeText?: string
            onPositiveClick?: () => void | Promise<void>
            onNegativeClick?: () => void
          }
    ): Promise<boolean> => {
      const options =
        typeof messageOrOptions === 'string'
          ? { title: 'Konfirmasi', content: messageOrOptions }
          : messageOrOptions

      return new Promise((resolve) => {
        dialog.warning({
          title: options.title,
          content: options.content,
          positiveText: options.positiveText || 'Ya',
          negativeText: options.negativeText || 'Batal',
          onPositiveClick: async () => {
            if (options.onPositiveClick) {
              await options.onPositiveClick()
            }
            resolve(true)
          },
          onNegativeClick: () => {
            if (options.onNegativeClick) {
              options.onNegativeClick()
            }
            resolve(false)
          },
        })
      })
    },

    confirmDelete: (options: {
      title?: string
      content?: string
      onConfirm: () => void | Promise<void>
    }) => {
      return dialog.error({
        title: options.title || 'Hapus Data',
        content: options.content || 'Apakah Anda yakin ingin menghapus data ini?',
        positiveText: 'Hapus',
        negativeText: 'Batal',
        onPositiveClick: options.onConfirm,
      })
    },

    alert: (options: {
      title: string
      content: string
      type?: 'info' | 'success' | 'warning' | 'error'
    }) => {
      const methodMap = {
        info: dialog.info,
        success: dialog.success,
        warning: dialog.warning,
        error: dialog.error,
      }
      const method = methodMap[options.type || 'info']
      return method({
        title: options.title,
        content: options.content,
        positiveText: 'OK',
      })
    },

    // Notifications (persistent)
    notify: {
      success: (options: { title: string; content?: string; duration?: number }) =>
        notification.success({
          title: options.title,
          content: options.content,
          duration: options.duration || 3000,
        }),
      error: (options: { title: string; content?: string; duration?: number }) =>
        notification.error({
          title: options.title,
          content: options.content,
          duration: options.duration || 5000,
        }),
      warning: (options: { title: string; content?: string; duration?: number }) =>
        notification.warning({
          title: options.title,
          content: options.content,
          duration: options.duration || 4000,
        }),
      info: (options: { title: string; content?: string; duration?: number }) =>
        notification.info({
          title: options.title,
          content: options.content,
          duration: options.duration || 3000,
        }),
    },
  }
}
