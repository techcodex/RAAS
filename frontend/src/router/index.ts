import { createRouter, createWebHistory } from 'vue-router'

import { useSessionStore } from '@/stores/session'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
      meta: { guest: true },
    },
    {
      path: '/admin/login',
      name: 'admin-login',
      component: () => import('@/views/AdminLoginView.vue'),
      meta: { guest: true, adminArea: true },
    },
    {
      path: '/',
      name: 'projects',
      component: () => import('@/views/ProjectsView.vue'),
    },
    {
      path: '/projects/:id',
      name: 'project',
      component: () => import('@/views/ProjectView.vue'),
      props: true,
    },
    {
      path: '/admin',
      component: () => import('@/components/AdminLayout.vue'),
      meta: { adminArea: true },
      children: [
        { path: '', redirect: { name: 'admin-dashboard' } },
        {
          path: 'dashboard',
          name: 'admin-dashboard',
          component: () => import('@/views/admin/AdminDashboardView.vue'),
        },
        {
          path: 'organizations',
          name: 'admin-organizations',
          component: () => import('@/views/admin/AdminOrganizationsView.vue'),
        },
        {
          path: 'settings',
          name: 'admin-settings',
          component: () => import('@/views/admin/AdminSettingsView.vue'),
        },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  const session = useSessionStore()
  if (!session.ready) await session.bootstrap()

  const isAdminArea = to.meta.adminArea === true

  // Guest pages (login / register / admin login): bounce authenticated users home.
  if (to.meta.guest) {
    if (session.isAuthenticated) {
      return session.isAdmin ? { name: 'admin-dashboard' } : { name: 'projects' }
    }
    return
  }

  // Protected admin pages.
  if (isAdminArea) {
    if (!session.isAuthenticated || !session.isAdmin) {
      return { name: 'admin-login' }
    }
    return
  }

  // Protected organization pages.
  if (!session.isAuthenticated) {
    return { name: 'login', query: to.path === '/' ? {} : { redirect: to.fullPath } }
  }
  if (session.isAdmin) {
    return { name: 'admin-dashboard' }
  }
})

export default router
