<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import TabBar from '@/components/TabBar.vue'

const route = useRoute()
const showTab = computed(() => route.meta && route.meta.tab)
</script>

<template>
  <div class="app-root">
    <router-view v-slot="{ Component }">
      <transition name="page-fade" mode="out-in">
        <component :is="Component" />
      </transition>
    </router-view>
    <TabBar v-if="showTab" :active="showTab" />
  </div>
</template>

<style>
.app-root {
  position: relative;
  max-width: 750px;
  margin: 0 auto;
  min-height: 100%;
  background: var(--gd-bg);
}
.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity 0.18s ease;
}
.page-fade-enter-from,
.page-fade-leave-to {
  opacity: 0;
}
</style>