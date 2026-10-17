# 静态资源说明

本目录存放 UI 图标/背景等**前端静态资源**（143 项，均已本地化）。

## 两条铁律

1. **不要在此目录下创建 `oss/` 子目录**
   `/static/oss/*` 是后端 Image 控制器提供的图片服务路径（见 SPEC §2.8），
   本地同名目录会遮蔽它，导致藏品图/Banner 裂图。

2. **不要把远端 URL 写死到代码或代理里**
   所有资源必须落盘到本目录，或由后端 `/static/oss/` 提供。
   校验：`python3 mock-server/check-offline.py`

## 已失效资源

9 个资源在原站 h5.gandart.shop 已 404（属原站自身缺陷，非本项目问题），
本目录放置了同名 SVG 占位图兜底，保证不出现裂图：
task-status.png、kline/card.jpg、kline/card2.jpg、logo.png、
my/avatar-bg.png、tabBar/index-unactive.png、tabBar/my-active.png、
compose/result-divider.png、copy-white.png、empty/empty-data.png、
icon/row-arrow.png、search-empty.png、setting.png、icon/gh/gh-{0,1,2}.png
