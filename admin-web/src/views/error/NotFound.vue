<script setup lang="ts">
</script>

<template>
  <el-result icon="warning" title="404" sub-title="页面不存在或已被移除">
    <template #extra>
      <router-link to="/dashboard">
        <el-button type="primary">返回首页</el-button>
      </router-link>
    </template>
  </el-result>
</template>
