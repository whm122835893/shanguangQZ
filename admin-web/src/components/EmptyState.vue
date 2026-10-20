<script setup lang="ts">
defineProps<{ text?: string }>()
</script>

<template>
  <div class="empty-state">
    <svg width="72" height="72" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity:.55">
      <rect x="14" y="20" width="44" height="34" rx="4" stroke="#c0c4cc" stroke-width="2" fill="#f5f7fa" />
      <path d="M14 30h44" stroke="#c0c4cc" stroke-width="2" />
      <circle cx="22" cy="25" r="2" fill="#c0c4cc" />
      <path d="M26 44l8-8 6 6 8-10" stroke="#c0c4cc" stroke-width="2" fill="none" />
    </svg>
    <p style="margin: 10px 0 0">{{ text ?? '暂无数据' }}</p>
  </div>
</template>
