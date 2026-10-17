/**
 * 构建前归档上一版 dist/assets，避免旧 HTML（被浏览器缓存）引用已删除的
 * hash 资源导致移动端白屏。构建后由 restore 把归档补回。
 */
const fs = require('fs')
const path = require('path')

const DIST = path.join(__dirname, 'dist')
const ASSETS = path.join(DIST, 'assets')
const ARCHIVE = path.join(__dirname, '.assets-archive')

const isAssetsDir = (d) => {
  try {
    return fs.statSync(d).isDirectory()
  } catch (e) {
    return false
  }
}

// 构建前：把当前 assets 归档
if (isAssetsDir(ASSETS)) {
  fs.rmSync(ARCHIVE, { recursive: true, force: true })
  fs.mkdirSync(ARCHIVE, { recursive: true })
  for (const f of fs.readdirSync(ASSETS)) {
    fs.renameSync(path.join(ASSETS, f), path.join(ARCHIVE, f))
  }
  console.log('[archive] 上一版 assets 已归档')
}

// 构建后：把归档补回（保留新旧共存）
if (isAssetsDir(ARCHIVE)) {
  for (const f of fs.readdirSync(ARCHIVE)) {
    const target = path.join(ASSETS, f)
    if (!fs.existsSync(target)) {
      fs.copyFileSync(path.join(ARCHIVE, f), target)
    }
  }
  console.log('[archive] 历史 assets 已补回，新旧共存')
}