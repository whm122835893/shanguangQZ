import { defineStore } from 'pinia'
import { getUserInfo } from '@/api'

/** 原站 token 以裸字符串存 localStorage；兼容历史 JSON 字符串格式 */
function readToken() {
  const raw = localStorage.getItem('token') || ''
  if (!raw) return ''
  try {
    const v = JSON.parse(raw)
    return typeof v === 'string' ? v : raw
  } catch (e) {
    return raw
  }
}

export const useUserStore = defineStore('user', {
  state: () => ({
    token: readToken(),
    userInfo: JSON.parse(localStorage.getItem('userInfo') || 'null')
  }),
  getters: {
    isLogin: (s) => !!s.token
  },
  actions: {
    setLogin(token, userInfo) {
      this.token = token
      this.userInfo = userInfo || null
      localStorage.setItem('token', token)
      if (userInfo) localStorage.setItem('userInfo', JSON.stringify(userInfo))
    },
    /** 原站行为：进入我的页时若已登录则拉取最新用户信息 */
    async fetchUserInfo() {
      if (!this.token) return null
      try {
        const res = await getUserInfo()
        const info = (res.data && (res.data.userinfo || res.data.userInfo)) || res.data
        if (info && Object.keys(info).length) {
          this.userInfo = { ...this.userInfo, ...info }
          localStorage.setItem('userInfo', JSON.stringify(this.userInfo))
        }
        return this.userInfo
      } catch (e) {
        return this.userInfo
      }
    },
    /** 隐私设置：对外隐藏所有藏品（原站 pages/my/privacy）
     *  原站实测默认为开启态，故初值取 true */
    setPrivacyHidden(hidden) {
      this.userInfo = { ...(this.userInfo || {}), is_hide_collection: hidden }
      localStorage.setItem('userInfo', JSON.stringify(this.userInfo))
    },
    logout() {
      this.token = ''
      this.userInfo = null
      localStorage.removeItem('token')
      localStorage.removeItem('userInfo')
    }
  }
})