<script setup lang="ts">
import { NDropdown, NButton, NIcon } from 'naive-ui'
import { EllipsisVertical } from '@vicons/ionicons5'

interface MenuItem {
  key: string
  label: string
  icon?: any
  disabled?: boolean
  danger?: boolean
}

interface Props {
  items: MenuItem[]
  trigger?: 'click' | 'hover'
  placement?: 'top' | 'bottom' | 'left' | 'right' | 'top-start' | 'top-end' | 'bottom-start' | 'bottom-end'
}

withDefaults(defineProps<Props>(), {
  trigger: 'click',
  placement: 'bottom-end'
})

const emit = defineEmits<{
  (e: 'select', key: string): void
}>()

function handleSelect(key: string) {
  emit('select', key)
}
</script>

<template>
  <NDropdown
    :options="items.map(item => ({
      key: item.key,
      label: item.label,
      disabled: item.disabled,
      props: item.danger ? { style: { color: '#ef4444' } } : undefined
    }))"
    :trigger="trigger"
    :placement="placement"
    @select="handleSelect"
  >
    <slot>
      <NButton quaternary circle>
        <template #icon>
          <NIcon>
            <EllipsisVertical />
          </NIcon>
        </template>
      </NButton>
    </slot>
  </NDropdown>
</template>
