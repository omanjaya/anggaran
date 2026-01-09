import { ref, computed, type Ref } from 'vue'
import api from '@/services/api'
import { useMessage } from './useMessage'

export interface CrudOptions<T, F> {
  /** API endpoint (e.g., '/programs') */
  endpoint: string
  /** Initial form values */
  initialForm: F
  /** Function to map item to form for editing */
  mapToForm?: (item: T) => F
  /** Function to transform form data before submit */
  transformPayload?: (form: F, isEditing: boolean) => any
  /** Callback after successful fetch */
  onFetchSuccess?: (data: T[]) => void
  /** Callback after successful save */
  onSaveSuccess?: () => void
  /** Callback after successful delete */
  onDeleteSuccess?: () => void
  /** Custom delete confirmation message */
  deleteConfirmMessage?: (item: T) => string
  /** Key to use for row identification */
  rowKey?: keyof T
}

export function useCrud<T extends { id: number }, F extends Record<string, any>>(
  options: CrudOptions<T, F>
) {
  const { success, error: showError, confirmDelete } = useMessage()

  // State
  const loading = ref(true)
  const saving = ref(false)
  const data = ref<T[]>([]) as Ref<T[]>
  const showModal = ref(false)
  const isEditing = ref(false)
  const editingItem = ref<T | null>(null) as Ref<T | null>
  const formError = ref('')
  const form = ref<F>({ ...options.initialForm }) as Ref<F>

  // Computed
  const isEmpty = computed(() => !loading.value && data.value.length === 0)
  const modalTitle = computed(() => isEditing.value ? 'Edit Data' : 'Tambah Data')

  // Fetch data
  async function fetchData(params?: Record<string, any>) {
    loading.value = true
    try {
      const response = await api.get(options.endpoint, { params })
      const responseData = response.data.data
      // Handle both paginated and direct array response
      data.value = Array.isArray(responseData)
        ? responseData.filter((item: T | null) => item != null)
        : (responseData?.data || []).filter((item: T | null) => item != null)
      options.onFetchSuccess?.(data.value)
    } catch (err) {
      console.error(`Failed to fetch ${options.endpoint}:`, err)
      data.value = []
    } finally {
      loading.value = false
    }
  }

  // Reset form to initial values
  function resetForm() {
    form.value = { ...options.initialForm }
    formError.value = ''
  }

  // Open modal for creating new item
  function openCreateModal() {
    isEditing.value = false
    editingItem.value = null
    resetForm()
    showModal.value = true
  }

  // Open modal for editing existing item
  function openEditModal(item: T) {
    isEditing.value = true
    editingItem.value = item
    formError.value = ''

    if (options.mapToForm) {
      form.value = options.mapToForm(item)
    } else {
      // Default: copy all properties
      form.value = { ...options.initialForm }
      Object.keys(options.initialForm).forEach((key) => {
        if (key in item) {
          (form.value as any)[key] = (item as any)[key] ?? (options.initialForm as any)[key]
        }
      })
      // Always include id for editing
      if ('id' in item) {
        (form.value as any).id = item.id
      }
    }
    showModal.value = true
  }

  // Close modal
  function closeModal() {
    showModal.value = false
    resetForm()
  }

  // Save (create or update)
  async function save() {
    saving.value = true
    formError.value = ''

    try {
      const payload = options.transformPayload
        ? options.transformPayload(form.value, isEditing.value)
        : form.value

      if (isEditing.value && editingItem.value) {
        await api.put(`${options.endpoint}/${editingItem.value.id}`, payload)
        success('Data berhasil diperbarui')
      } else {
        await api.post(options.endpoint, payload)
        success('Data berhasil ditambahkan')
      }

      closeModal()
      await fetchData()
      options.onSaveSuccess?.()
    } catch (err: any) {
      if (err.response?.data?.errors) {
        const errors = err.response.data.errors
        formError.value = Object.values(errors).flat().join(', ')
      } else {
        formError.value = err.response?.data?.message || 'Gagal menyimpan data'
      }
    } finally {
      saving.value = false
    }
  }

  // Delete item
  async function deleteItem(item: T) {
    const message = options.deleteConfirmMessage?.(item) || 'Apakah Anda yakin ingin menghapus data ini?'

    confirmDelete({
      content: message,
      onConfirm: async () => {
        try {
          await api.delete(`${options.endpoint}/${item.id}`)
          success('Data berhasil dihapus')
          await fetchData()
          options.onDeleteSuccess?.()
        } catch (err: any) {
          showError(err.response?.data?.message || 'Gagal menghapus data')
        }
      },
    })
  }

  // Toggle active status
  async function toggleActive(item: T & { is_active?: boolean }) {
    try {
      await api.put(`${options.endpoint}/${item.id}`, {
        ...item,
        is_active: !item.is_active,
      })
      success('Status berhasil diubah')
      await fetchData()
    } catch (err: any) {
      showError(err.response?.data?.message || 'Gagal mengubah status')
    }
  }

  return {
    // State
    loading,
    saving,
    data,
    showModal,
    isEditing,
    editingItem,
    formError,
    form,

    // Computed
    isEmpty,
    modalTitle,

    // Methods
    fetchData,
    resetForm,
    openCreateModal,
    openEditModal,
    closeModal,
    save,
    deleteItem,
    toggleActive,
  }
}
