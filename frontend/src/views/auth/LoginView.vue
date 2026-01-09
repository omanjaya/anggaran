<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { NCard, NButton, NInput, NAlert } from 'naive-ui'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function handleLogin() {
  loading.value = true
  error.value = ''

  try {
    await authStore.login(email.value, password.value)
    const redirect = route.query.redirect as string
    router.push(redirect || { name: 'dashboard' })
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Login gagal. Periksa kembali email dan password.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-900 to-blue-700">
    <div class="w-full max-w-md">
      <NCard class="shadow-xl">
        <div class="text-center mb-8">
          <h1 class="text-3xl font-bold text-gray-800">SIPERA</h1>
          <p class="text-gray-600 mt-2">Sistem Informasi Perencanaan dan Realisasi Anggaran</p>
          <p class="text-sm text-gray-500">Dinas Kominfo Provinsi Bali</p>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-6">
          <NAlert v-if="error" type="error" :show-icon="true" class="mb-4">
            {{ error }}
          </NAlert>

          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <NInput
              id="email"
              v-model:value="email"
              type="text"
              placeholder="nama@email.com"
              size="large"
            />
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <NInput
              id="password"
              v-model:value="password"
              type="password"
              placeholder="********"
              show-password-on="click"
              size="large"
            />
          </div>

          <NButton
            type="primary"
            attr-type="submit"
            :loading="loading"
            :disabled="loading"
            block
            size="large"
          >
            {{ loading ? 'Memproses...' : 'Masuk' }}
          </NButton>
        </form>
      </NCard>

      <p class="text-center text-white/70 mt-4 text-sm">
        &copy; {{ new Date().getFullYear() }} Dinas Kominfo Provinsi Bali
      </p>
    </div>
  </div>
</template>
