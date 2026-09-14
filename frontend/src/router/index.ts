import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import LoginView from '@/views/LoginView.vue'
import OrganizationsView from '@/views/OrganizationsView.vue'
import OrganizationDetailView from '@/views/OrganizationDetailView.vue'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    redirect: '/organizations',
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { requiresGuest: true },
  },
  {
    path: '/organizations',
    name: 'organizations',
    component: OrganizationsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/organizations/:id',
    name: 'organization-detail',
    component: OrganizationDetailView,
    meta: { requiresAuth: true },
  },
  {
    path: '/admin',
    alias: '/proxies',
    name: 'admin',
    component: () => import('@/views/AdminView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/organizations',
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to, _from, next) => {
  const token = localStorage.getItem('auth_token')
  const isAuthenticated = !!token

  let isAdmin = false
  const userStr = localStorage.getItem('auth_user')
  if (userStr) {
    try {
      const parsed = JSON.parse(userStr) as { is_admin?: boolean }
      isAdmin = !!parsed.is_admin
    } catch {
      isAdmin = false
    }
  }

  if (to.meta.requiresAuth && !isAuthenticated) {
    next({ name: 'login' })
  } else if (to.meta.requiresGuest && isAuthenticated) {
    next({ name: 'organizations' })
  } else if (to.meta.requiresAdmin && !isAdmin) {
    next({ name: 'organizations' })
  } else {
    next()
  }
})
