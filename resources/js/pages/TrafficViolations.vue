<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
</script>

<template>
  <AppSidebarLayout :breadcrumbs="[{ title: 'Traffic violations', href: '/traffic-violations' }]">
    <div class="p-8">
      <h1 class="text-2xl font-bold mb-4">Traffic violations</h1>
      <div class="flex flex-wrap gap-6">
        <a href="/fines" class="w-64 h-40 bg-white rounded-lg shadow flex items-center justify-center overflow-hidden hover:ring-2 hover:ring-blue-400 transition cursor-pointer">
          <img src="/img/rta.png" alt="Box 1" class="object-contain max-h-full max-w-full" />
        </a>
        <a href="/traffic-violations/salik" class="w-64 h-40 bg-white rounded-lg shadow flex items-center justify-center overflow-hidden hover:ring-2 hover:ring-blue-400 transition cursor-pointer">
          <img src="/img/c3a9d8db07d9.jpg" alt="Box 2" class="object-contain max-h-full max-w-full" />
        </a>
      </div>
    </div>
  </AppSidebarLayout>
</template>
