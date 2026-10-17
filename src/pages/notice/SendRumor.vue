<script setup>
/**
 * 发表 rumor —— 1:1 复刻原站 pages/notice/sendRumor
 *
 * 路由：#/pages/notice/sendRumor
 *
 * 原站 390x844 实测：
 *   .container.page-container.diy @0,0 390x844  背景透明
 *     padding 44px 0 45px
 *     .diy-nav                  @0,0 390x44
 *       导航行                    @0,8 390x29  padding 0 16px
 *         「取消」              @16,12 28x21  14px/400 rgb(51,51,51)
 *         「发表」按钮          @320,8 54x29  bg rgb(227,227,227)  br8
 *                               14px/700 rgb(102,102,102)
 *     .plr-16                   @0,44 390x262
 *       输入卡                   @16,44 358x88  bg #fff  br4  padding 9px
 *         textarea              @25,53 340x70（placeholder「请输入内容」）
 *       上传区                   @16,132 358x120
 *         照片卡                 @16,148 104x104  bg #fff
 *           img.png             @52,177 32x32
 *           「照片」            @58,209 20x15  10px/400 rgb(186,186,186)
 *     匿名发表行                 @16,252 358x54  padding-top 33
 *       「匿名发表」            @16,285 56x21  14px/400 rgb(122,122,122)
 *       u-switch（关）          @84,287 34x18  track rgb(230,230,230)
 *         node                  @85,288 16x16  #fff（偏左 = 关闭态）
 *
 * 空态说明：账号当前未进入编辑态，内容为空，故卡片与发布按钮均为初始态。
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const content = ref('')
/** 匿名发表开关：默认关闭（原站一致） */
const anonymous = ref(false)
const publishing = ref(false)

const onCancel = () => {
  router.back()
}

const onPublish = () => {
  /* 原站在此校验内容并提交，未填写时按钮保持灰色不可提交 */
  if (!content.value) return
  publishing.value = true
}
</script>

<template>
  <div class="send-rumor">
    <!-- 导航 @0,0 390x44 -->
    <div class="diy-nav">
      <div class="nav-row">
        <div class="cancel" @click="onCancel">取消</div>
        <div class="publish" :class="{ 'publish--active': !!content }" @click="onPublish">
          发表
        </div>
      </div>
    </div>

    <!-- 内容区 @0,44 -->
    <div class="body">
      <!-- 输入卡 @16,44 358x88 -->
      <div class="input-card">
        <textarea v-model="content" class="input-field" placeholder="请输入内容"></textarea>
      </div>

      <!-- 上传区 @16,132 -->
      <div class="upload">
        <div class="photo-card">
          <img class="photo-icon" src="/static/icon/img.png" alt="" />
          <span class="photo-text">照片</span>
        </div>
      </div>

      <!-- 匿名发表 @16,252 -->
      <div class="anon-row">
        <span class="anon-text">匿名发表</span>
        <div
          class="switch"
          :class="{ 'switch--on': anonymous }"
          @click="anonymous = !anonymous"
        >
          <div class="switch-node"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.send-rumor {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  padding: 44px 0 45px;
  background: #fff;
  box-sizing: border-box;
  overflow: hidden;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

/* ---------- 导航 @0,0 390x44 ---------- */
.diy-nav {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 44px;
  display: flex;
  align-items: center;
}
.nav-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 390px;
  height: 29px;
  padding: 0 16px;
  box-sizing: border-box;
}
/* 「取消」@16,12 28x21 */
.cancel {
  font-size: 14px;
  font-weight: 400;
  color: rgb(51, 51, 51);
  line-height: 21px;
  cursor: pointer;
}
/* 「发表」@320,8 54x29 */
.publish {
  width: 54px;
  height: 29px;
  background: rgb(227, 227, 227);
  border-radius: 8px;
  font-size: 14px;
  font-weight: 700;
  color: rgb(102, 102, 102);
  line-height: 29px;
  text-align: center;
  box-sizing: border-box;
  cursor: pointer;
}

/* ---------- 内容区 ---------- */
.body {
  position: absolute;
  left: 0;
  top: 44px;
  width: 390px;
  height: 262px;
  padding: 0 16px;
  box-sizing: border-box;
}

/* 输入卡 @16,44 358x88 */
.input-card {
  width: 358px;
  height: 88px;
  padding: 9px;
  background: #fff;
  border-radius: 4px;
  box-sizing: border-box;
}
.input-field {
  width: 340px;
  height: 70px;
  border: none;
  outline: none;
  resize: none;
  background: transparent;
  font-size: 15px;
  font-weight: 400;
  color: rgb(0, 0, 0);
  font-family: inherit;
  line-height: 21px;
}
.input-field::placeholder {
  color: rgb(186, 186, 186);
}

/* 上传区 @16,132 358x120 */
.upload {
  width: 358px;
  height: 120px;
}
/* 照片卡 @16,148 104x104 */
.photo-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 104px;
  height: 104px;
  margin-top: 16px;
  background: #fff;
  box-sizing: border-box;
}
.photo-icon {
  display: block;
  width: 32px;
  height: 32px;
}
/* 「照片」@58,209 */
.photo-text {
  margin-top: 0;
  font-size: 10px;
  font-weight: 400;
  color: rgb(186, 186, 186);
  line-height: 15px;
}

/* 匿名发表 @16,252 358x54（padding-top 33） */
.anon-row {
  display: flex;
  align-items: center;
  width: 358px;
  height: 54px;
  padding-top: 33px;
  box-sizing: border-box;
}
/* 「匿名发表」@16,285 */
.anon-text {
  margin-right: 12px;
  font-size: 14px;
  font-weight: 400;
  color: rgb(122, 122, 122);
  line-height: 21px;
}
/* 开关 @84,287 34x18 */
.switch {
  position: relative;
  width: 34px;
  height: 18px;
  background: rgb(230, 230, 230);
  border-radius: 100px;
  box-sizing: border-box;
  cursor: pointer;
}
/* 节点 @85,288 16x16 —— 关闭态偏左 */
.switch-node {
  position: absolute;
  left: 1px;
  top: 1px;
  width: 16px;
  height: 16px;
  background: #fff;
  border-radius: 50%;
}
.switch--on {
  background: rgb(21, 124, 255);
}
.switch--on .switch-node {
  left: 17px;
}
</style>