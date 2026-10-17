<script setup>
/**
 * 收货地址 —— 1:1 复刻原站 pages/my/address/list
 * 由 设置页「收货地址」/ 其他功能页「收货地址」进入。
 *
 * 原站登录态实测（getBoundingClientRect + getComputedStyle）：
 *   .container 高 844 白底
 *   返回 back-btn.png@13,10 24x24
 *   导航标题「收货地址」@165,16 60x16  15px/700 rgb(51,51,51)
 *
 *   空态图 addr-empty.png @134.5,184 121x100（原图 612x504）
 *   按钮「去添加收货地址」@126.5,316 137x34
 *     bg rgb(0,0,0)  12px/700 #fff 圆角（btn-size-sm，按胶囊处理）
 *
 * 接口（原站实测 200）：POST user/address_list
 * 当前账号无地址数据，故渲染空态；与原站一致，不编造地址。
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { http } from '@/api/http'

const router = useRouter()

const list = ref([])

async function load() {
  try {
    const r = await http.post('/user/address_list', {})
    const d = r.data
    list.value = (d && (d.list || d.data || (Array.isArray(d) ? d : []))) || []
  } catch (e) {
    list.value = []
  }
}

onMounted(load)

function toEdit() { router.push('/pages/my/address/edit') }
</script>

<template>
  <div class="addr-list-page">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">收货地址</div>
    </div>

    <!-- 空态 @134.5,184 121x100 -->
    <div v-if="!list.length" class="empty">
      <img class="empty-img" src="/static/empty/addr-empty.png" alt="暂无收货地址" />
      <!-- 按钮 @126.5,316 137x34 -->
      <div class="add-btn" @click="toEdit">去添加收货地址</div>
    </div>

    <!-- 有数据时的地址列表（原站当前无数据，此分支按接口字段预留） -->
    <div v-else class="list">
      <div v-for="(a, i) in list" :key="i" class="addr-item">
        <div class="addr-name">{{ a.name || a.consignee }}</div>
        <div class="addr-phone">{{ a.mobile || a.phone }}</div>
        <div class="addr-detail">{{ a.address || a.detail }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* .container 高 844 白底 */
.addr-list-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
}

/* 导航：标题「收货地址」@165,16 60x16
   注：标题需居中于页面（195），同时让出左侧返回按钮点击区域 */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #333; font-size: 15px; font-weight: 700;
}

/* 空态容器：从 y=184 开始，避开导航区 */
.empty {
  position: absolute;
  left: 0;
  top: 184px;
  width: 390px;
  height: 170px;
}
/* 空态图 @134.5,184 121x100 —— 相对容器 left:134.5 top:0 */
.empty-img {
  position: absolute;
  left: 134.5px;
  top: 0;
  width: 121px;
  height: 100px;
}
/* 按钮 @126.5,316 137x34 —— 相对容器 top:316-184=132 */
.add-btn {
  position: absolute;
  left: 126.5px;
  top: 132px;
  width: 137px;
  height: 34px;
  line-height: 34px;
  text-align: center;
  background: #000;
  border-radius: 17px;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
}

.list { position: absolute; left: 0; top: 44px; width: 390px; padding: 0 16px; }
.addr-item {
  padding: 14px 0;
  border-bottom: 1px solid #eee;
}
.addr-name { font-size: 14px; color: #333; }
.addr-phone { font-size: 13px; color: #666; margin-top: 4px; }
.addr-detail { font-size: 12px; color: #999; margin-top: 4px; }
</style>