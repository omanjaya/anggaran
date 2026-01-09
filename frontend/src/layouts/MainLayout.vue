<script setup lang="ts">
import { h, ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { NLayout, NLayoutSider, NLayoutHeader, NLayoutContent, NMenu, NButton, NTag, NIcon, NDropdown, NAvatar } from 'naive-ui'
import type { MenuOption } from 'naive-ui'
import {
  HomeOutline,
  ServerOutline,
  PeopleOutline,
  CalendarOutline,
  CheckmarkCircleOutline,
  BarChartOutline,
  SettingsOutline,
  MenuOutline,
  LogOutOutline,
  PersonOutline,
} from '@vicons/ionicons5'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const collapsed = ref(false)

const user = computed(() => authStore.user)

const activeKey = computed(() => route.name as string)

function renderIcon(icon: any) {
  return () => h(NIcon, null, { default: () => h(icon) })
}

const menuOptions = computed<MenuOption[]>(() => {
  const items: MenuOption[] = [
    {
      label: 'Dashboard',
      key: 'dashboard',
      icon: renderIcon(HomeOutline),
    },
  ]

  if (authStore.hasPermission('master.view')) {
    items.push({
      label: 'Master Data',
      key: 'master',
      icon: renderIcon(ServerOutline),
      children: [
        { label: 'Program', key: 'programs' },
        { label: 'Kegiatan', key: 'activities' },
        { label: 'Sub Kegiatan', key: 'sub-activities' },
        { label: 'Item Anggaran', key: 'budget-items' },
        { label: 'Kode Rekening', key: 'account-codes' },
      ],
    })
  }

  if (authStore.hasPermission('users.view')) {
    items.push({
      label: 'Pengguna',
      key: 'users',
      icon: renderIcon(PeopleOutline),
    })
  }

  if (authStore.hasPermission('planning.view')) {
    items.push({
      label: 'Perencanaan',
      key: 'planning',
      icon: renderIcon(CalendarOutline),
      children: [
        { label: 'Rencana Bulanan', key: 'monthly-planning' },
        { label: 'Entry DPA', key: 'dpa-entry' },
        { label: 'PLGK Generator', key: 'plgk-generator' },
        { label: 'ROK OP', key: 'operational-schedule' },
      ],
    })
  }

  if (authStore.hasPermission('realization.view')) {
    items.push({
      label: 'Realisasi',
      key: 'realization',
      icon: renderIcon(CheckmarkCircleOutline),
      children: [
        { label: 'Input Realisasi', key: 'monthly-realization' },
        { label: 'Verifikasi', key: 'verification' },
        { label: 'Persetujuan', key: 'approval' },
      ],
    })
  }

  if (authStore.hasPermission('reports.view')) {
    items.push({
      label: 'Laporan',
      key: 'reports',
      icon: renderIcon(BarChartOutline),
      children: [
        { label: 'Ringkasan', key: 'summary-report' },
        { label: 'Per Kategori', key: 'category-report' },
        { label: 'Realisasi', key: 'realisasi-report' },
        { label: 'Custom Report', key: 'custom-report' },
        { label: 'Peringatan Deviasi', key: 'deviation-alerts' },
      ],
    })
  }

  if (authStore.hasPermission('master.manage')) {
    items.push({
      label: 'Pengaturan',
      key: 'settings',
      icon: renderIcon(SettingsOutline),
      children: [
        { label: 'Import Excel', key: 'data-import' },
        { label: 'Import DPA PDF', key: 'dpa-pdf-import' },
        { label: 'Manajemen SKPD', key: 'skpd-management' },
        { label: 'Audit Log', key: 'audit-log' },
      ],
    })
  }

  return items
})

const userDropdownOptions = [
  {
    label: 'Profil',
    key: 'profile',
    icon: renderIcon(PersonOutline),
  },
  {
    type: 'divider',
    key: 'd1',
  },
  {
    label: 'Logout',
    key: 'logout',
    icon: renderIcon(LogOutOutline),
  },
]

function handleMenuSelect(key: string) {
  router.push({ name: key })
}

async function handleUserAction(key: string) {
  if (key === 'logout') {
    await authStore.logout()
    router.push({ name: 'login' })
  } else if (key === 'profile') {
    // Handle profile navigation if needed
  }
}
</script>

<template>
  <NLayout has-sider position="absolute" style="top: 0; bottom: 0; left: 0; right: 0;">
    <!-- Sidebar -->
    <NLayoutSider
      bordered
      collapse-mode="width"
      :collapsed-width="64"
      :width="240"
      :collapsed="collapsed"
      show-trigger
      @collapse="collapsed = true"
      @expand="collapsed = false"
      :native-scrollbar="false"
      content-style="display: flex; flex-direction: column;"
    >
      <div class="sidebar-header">
        <h1 v-if="!collapsed" class="text-xl font-bold text-white">SIPERA</h1>
        <h1 v-else class="text-xl font-bold text-white">S</h1>
      </div>
      <NMenu
        :collapsed="collapsed"
        :collapsed-width="64"
        :collapsed-icon-size="22"
        :options="menuOptions"
        :value="activeKey"
        :root-indent="24"
        :indent="12"
        inverted
        @update:value="handleMenuSelect"
        style="flex: 1;"
      />
    </NLayoutSider>

    <NLayout>
      <!-- Header -->
      <NLayoutHeader bordered class="app-header">
        <NButton quaternary circle @click="collapsed = !collapsed">
          <template #icon>
            <NIcon><MenuOutline /></NIcon>
          </template>
        </NButton>

        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-600">{{ user?.name }}</span>
          <NTag type="info" size="small">{{ user?.role }}</NTag>
          <NDropdown :options="userDropdownOptions" @select="handleUserAction">
            <NAvatar round size="small" class="cursor-pointer">
              {{ user?.name?.charAt(0) || 'U' }}
            </NAvatar>
          </NDropdown>
        </div>
      </NLayoutHeader>

      <!-- Content -->
      <NLayoutContent content-style="padding: 24px; background: #f5f7fa;" :native-scrollbar="false">
        <router-view />
      </NLayoutContent>
    </NLayout>
  </NLayout>
</template>

<style scoped>
.sidebar-header {
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  flex-shrink: 0;
}

.app-header {
  height: 64px;
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
}

:deep(.n-layout-sider) {
  background: linear-gradient(180deg, #1e3a5f 0%, #152a45 100%) !important;
}

:deep(.n-layout-sider-scroll-container) {
  display: flex;
  flex-direction: column;
}

:deep(.n-menu) {
  background: transparent !important;
}

:deep(.n-menu .n-menu-item-content) {
  color: rgba(255, 255, 255, 0.7) !important;
}

:deep(.n-menu .n-menu-item-content:hover) {
  color: white !important;
  background: rgba(255, 255, 255, 0.05) !important;
}

:deep(.n-menu .n-menu-item-content--selected),
:deep(.n-menu .n-menu-item-content--child-active) {
  color: white !important;
  background: rgba(255, 255, 255, 0.1) !important;
}

:deep(.n-menu-item-group-title) {
  color: rgba(255, 255, 255, 0.5) !important;
}

:deep(.n-layout-sider__border) {
  background-color: rgba(255, 255, 255, 0.1) !important;
}

:deep(.n-layout-toggle-button) {
  background: #1e3a5f !important;
  color: white !important;
}

:deep(.n-layout-toggle-button:hover) {
  background: #2a4a6f !important;
}
</style>
