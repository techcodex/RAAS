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
      name: 'admin',
      component: () => import('@/views/AdminView.vue'),
      meta: { adminArea: true },
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
      return session.isAdmin ? { name: 'admin' } : { name: 'projects' }
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
    return { name: 'admin' }
  }
})

export default router
