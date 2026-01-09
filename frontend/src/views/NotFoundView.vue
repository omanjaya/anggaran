<script setup lang="ts">
import { useRouter } from 'vue-router'
import { NButton, NResult } from 'naive-ui'

const router = useRouter()

function goHome() {
  router.push({ name: 'dashboard' })
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <NResult status="404" title="404" description="Halaman tidak ditemukan">
      <template #footer>
        <NButton type="primary" @click="goHome">
          Kembali ke Dashboard
        </NButton>
      </template>
    </NResult>
  </div>
</template>
