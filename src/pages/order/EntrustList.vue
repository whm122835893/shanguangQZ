<script setup>
/** 我的委托 —— 原站 /pages/order/entrust/list（委托 = 我作为卖家挂出的单） */
import TradeList from './TradeList.vue'
</script>

<template>
  <TradeList title="我的委托" :is-entrust="true" />
</template>
