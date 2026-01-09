<script setup lang="ts">
import { NTag } from 'naive-ui'
import { computed } from 'vue'

type StatusType = 'success' | 'warning' | 'error' | 'info' | 'default'

interface Props {
  status: string | boolean
  type?: StatusType
  label?: string
  size?: 'small' | 'medium' | 'large'
  // Preset mappings
  activeLabel?: string
  inactiveLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  size: 'small',
  activeLabel: 'Aktif',
  inactiveLabel: 'Nonaktif'
})

const statusConfig = computed(() => {
  // Handle boolean (active/inactive)
  if (typeof props.status === 'boolean') {
    return {
      type: props.status ? 'success' : 'default',
      label: props.status ? props.activeLabel : props.inactiveLabel
    }
  }

  // Handle string status with preset mappings
  const statusMap: Record<string, { type: StatusType; label: string }> = {
    // Common statuses
    'active': { type: 'success', label: 'Aktif' },
    'inactive': { type: 'default', label: 'Nonaktif' },
    'pending': { type: 'warning', label: 'Pending' },
    'approved': { type: 'success', label: 'Disetujui' },
    'rejected': { type: 'error', label: 'Ditolak' },
    'draft': { type: 'info', label: 'Draft' },
    'submitted': { type: 'info', label: 'Diajukan' },
    'verified': { type: 'success', label: 'Terverifikasi' },
    // Alert statuses
    'ACTIVE': { type: 'error', label: 'Aktif' },
    'ACKNOWLEDGED': { type: 'warning', label: 'Diakui' },
    'RESOLVED': { type: 'success', label: 'Selesai' },
    'DISMISSED': { type: 'default', label: 'Diabaikan' },
    // Severity
    'CRITICAL': { type: 'error', label: 'Kritis' },
    'HIGH': { type: 'warning', label: 'Tinggi' },
    'MEDIUM': { type: 'warning', label: 'Sedang' },
    'LOW': { type: 'info', label: 'Rendah' },
  }

  const config = statusMap[props.status.toLowerCase()] || statusMap[props.status]

  return {
    type: props.type || config?.type || 'default',
    label: props.label || config?.label || props.status
  }
})

const tagType = computed(() => {
  const typeMap: Record<StatusType, 'success' | 'warning' | 'error' | 'info' | 'default'> = {
    success: 'success',
    warning: 'warning',
    error: 'error',
    info: 'info',
    default: 'default'
  }
  return typeMap[statusConfig.value.type as StatusType]
})
</script>

<template>
  <NTag :type="tagType" :size="size" round>
    {{ statusConfig.label }}
  </NTag>
</template>
