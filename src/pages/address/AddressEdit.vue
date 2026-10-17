<script setup>
/**
 * 添加收货地址 —— 1:1 复刻原站 pages/my/address/edit
 * 由 收货地址列表页「去添加收货地址」进入。
 *
 * 原站登录态实测（getBoundingClientRect + getComputedStyle）：
 *   .container 高 844 白底
 *   返回 back-btn.png @13,10 24x24
 *   导航标题「添加收货地址」@150,16 90x16  15px/700 rgb(51,51,51)
 *
 *   表单行（标签 @32，输入框占位 @125，行距恒定 54px）：
 *     收货人    标签 @32,89.5 42x15   占位「请输入姓名」@125,82 233x26
 *     手机号码  标签 @32,143.5 56x15  占位「请输入手机号」@125,136
 *     所在地区  标签 @32,197.5 56x15  占位「请选择市区」@125,190
 *     详细地址  标签 @32,251.5 56x15  占位「街道 小区 门牌号等」@125,244
 *   标签统一 14px/400 rgb(153,153,153)
 *   占位统一 14px/400 rgb(192,196,204)  line-height 26px
 *   开关「设置默认收货地址」标签 @32,328.5 112x15
 *   保存按钮 @16,773 358x47  bg rgb(227,227,227)  14px/700 rgb(102,102,102)
 *
 * 接口：原站保存时调用 user/address_save（本页未提交，故不预设请求时机）
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'

const router = useRouter()

const form = ref({
  name: '',
  mobile: '',
  region: '',
  detail: '',
  isDefault: false
})

/* 表单 4 行定义，行首页面 y = 82 + i*54（行距恒定） */
const fields = [
  { key: 'name', label: '收货人', ph: '请输入姓名' },
  { key: 'mobile', label: '手机号码', ph: '请输入手机号' },
  { key: 'region', label: '所在地区', ph: '请选择市区' },
  { key: 'detail', label: '详细地址', ph: '街道 小区 门牌号等' }
]

function onSave() {
  if (!form.value.name) return showToast({ icon: 'none', message: '请输入收货人' })
  if (!/^1\d{10}$/.test(form.value.mobile)) return showToast({ icon: 'none', message: '请输入正确的手机号' })
  if (!form.value.region) return showToast({ icon: 'none', message: '请选择所在地区' })
  if (!form.value.detail) return showToast({ icon: 'none', message: '请输入详细地址' })
  showToast({ icon: 'none', message: '保存成功' })
}
</script>

<template>
  <div class="addr-edit-page">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">添加收货地址</div>
    </div>

    <!-- 表单 4 行，页面行首 y = 85 + i*54（行距恒定 54px）。
         .form 容器 top=85，故行内 top = i*54 -->
    <div class="form">
      <div v-for="(f, i) in fields" :key="f.key" class="field" :style="{ top: i * 54 + 'px' }">
        <span class="label">{{ f.label }}</span>
        <input
          v-model="form[f.key]"
          class="input"
          type="text"
          :placeholder="f.ph"
        />
      </div>

      <!-- 开关行 @32,328.5 -->
      <div class="switch-row">
        <span class="label">设置默认收货地址</span>
        <!-- 原站为 uni-switch 胶囊开关，此处按原站实测外观自绘（35x20 关闭态） -->
        <span
          class="switch"
          :class="{ on: form.isDefault }"
          @click="form.isDefault = !form.isDefault"
        ><span class="knob"></span></span>
      </div>
    </div>

    <!-- 保存按钮 @16,773 358x47（置底，页面高 844） -->
    <div class="save-btn" @click="onSave">保存</div>
  </div>
</template>

<style scoped>
/* .container 高 844 白底 */
.addr-edit-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
}

/* 导航：标题「添加收货地址」@150,16 90x16（居中于 195） */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #333; font-size: 15px; font-weight: 700;
}

/* 表单容器：从首行 y=85 开始（原站标签 y=89，行内 26px 垂直居中后行首=85），避开导航区 */
.form { position: absolute; left: 0; top: 85px; width: 390px; height: 300px; }
.field {
  position: absolute;
  left: 32px;
  width: 326px;
  height: 26px;
  display: flex;
  align-items: center;
}
/* 标签 14px/400 #999（原站标签底 y 比占位低 7.5px，此处垂直居中于 26px 行） */
.label {
  width: 93px;
  flex-shrink: 0;
  color: #999;
  font-size: 14px;
  font-weight: 400;
  line-height: 26px;
}
/* 输入框占位 14px/400 #c0c4cc —— 原站占位 @125，即 32+93 */
.input {
  flex: 1;
  height: 26px;
  padding: 0;
  border: none;
  outline: none;
  background: transparent;
  color: #333;
  font-size: 14px;
  line-height: 26px;
}
.input::placeholder { color: #c0c4cc; }

/* 开关行：原站标签 @32,328.5 112x15
   行首 y = 328.5 - (26-15)/2 = 323（26px 行内垂直居中，文字 15px）
   容器 top=82，故行内 top = 324-85 = 239 */
.switch-row {
  position: absolute;
  left: 32px;
  top: 239px;
  width: 326px;
  height: 26px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.switch-row .label { width: auto; }

/* 开关胶囊：原站截图实测约 35x20，关闭态浅灰底、白色滑块靠左 */
.switch {
  position: relative;
  width: 35px;
  height: 20px;
  flex-shrink: 0;
  border-radius: 10px;
  background: #e5e5e5;
  transition: background 0.2s;
  cursor: pointer;
}
.switch.on { background: #1989fa; }
.knob {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #fff;
  transition: transform 0.2s;
}
.switch.on .knob { transform: translateX(15px); }

/* 保存按钮 @16,773 358x47 bg rgb(227,227,227) */
.save-btn {
  position: absolute;
  left: 16px;
  top: 773px;
  width: 358px;
  height: 47px;
  line-height: 47px;
  text-align: center;
  background: rgb(227, 227, 227);
  border-radius: 24px;
  color: #666;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}
</style>