import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '@/types/models'
import api from '@/services/api'

// Helper to safely parse JSON from localStorage
function getStoredJson<T>(key: string): T | null {
  try {
    const item = localStorage.getItem(key)
    return item ? JSON.parse(item) : null
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', () => {
  // Restore from localStorage on init
  const user = ref<User | null>(getStoredJson<User>('user'))
  const token = ref<string | null>(localStorage.getItem('token'))
  const permissions = ref<string[]>(getStoredJson<string[]>('permissions') || [])
  const initialized = ref(false)

  // Check if authenticated - only need token for initial check
  const isAuthenticated = computed(() => !!token.value)

  const hasPermission = (permission: string): boolean => {
    if (!user.value) return false
    if (user.value.role === 'ADMIN') return true
    return permissions.value.includes(permission)
  }

  const hasRole = (role: string): boolean => {
    return user.value?.role === role
  }

  function saveToStorage() {
    if (token.value) {
      localStorage.setItem('token', token.value)
    } else {
      localStorage.removeItem('token')
    }
    if (user.value) {
      localStorage.setItem('user', JSON.stringify(user.value))
    } else {
      localStorage.removeItem('user')
    }
    if (permissions.value.length > 0) {
      localStorage.setItem('permissions', JSON.stringify(permissions.value))
    } else {
      localStorage.removeItem('permissions')
    }
  }

  function clearStorage() {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    localStorage.removeItem('permissions')
  }

  async function login(email: string, password: string) {
    const response = await api.post('/auth/login', { email, password })
    token.value = response.data.token
    user.value = response.data.user
    permissions.value = response.data.permissions || []
    saveToStorage()
    initialized.value = true
    return response.data
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch {
      // Ignore logout errors
    } finally {
      token.value = null
      user.value = null
      permissions.value = []
      clearStorage()
      initialized.value = false
    }
  }

  async function fetchUser() {
    if (!token.value) {
      initialized.value = true
      return null
    }
    try {
      const response = await api.get('/auth/me')
      user.value = response.data.user
      permissions.value = response.data.permissions || []
      saveToStorage()
      initialized.value = true
      return user.value
    } catch {
      // Token invalid - clear everything
      token.value = null
      user.value = null
      permissions.value = []
      clearStorage()
      initialized.value = true
      return null
    }
  }

  return {
    user,
    token,
    permissions,
    initialized,
    isAuthenticated,
    hasPermission,
    hasRole,
    login,
    logout,
    fetchUser,
  }
})
