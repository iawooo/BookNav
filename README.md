# 书签导航

![License](https://img.shields.io/github/license/username/bookmark-navigator) ![Size](https://img.shields.io/github/size/username/bookmark-navigator/dist/bookmark-navigator.zip) ![PHP](https://img.shields.io/badge/PHP-7.4+-blue) ![MySQL](https://img.shields.io/badge/MySQL-5.6+-orange)

**书签导航** 是一个轻量级、优雅的个人书签管理工具，灵感来源于 OneNav，专注于简单易用和高效部署。项目源代码不到 80KB，仅依赖 PHP 和 MySQL，无需复杂环境即可在虚拟主机上运行。支持明暗主题、拖拽排序、樱花特效以及高级图标抓取，让你的书签管理既实用又美观。

## 亮点

- **超轻量级**：源代码不到 100KB，占用空间小，加载快。
- **简单部署**：只需 PHP 和 MySQL，无需额外依赖，虚拟主机即可运行。
- **灵感来源 OneNav**：继承了 OneNav 的简洁设计，同时优化了功能和用户体验。
- **高级图标抓取**：自动从网页提取最佳图标（支持 `favicon.ico`、HTML 链接、Manifest 等），类似 Favicon Finder。
- **拖拽排序**：支持书签拖拽重新排序，保存位置到数据库。
- **明暗主题**：内置炫酷的渐变背景，支持一键切换明暗模式。
- **樱花特效**：动态樱花飘落效果，提升视觉体验。
- **密码保护**：简单登录机制，确保书签隐私。
- **响应式设计**：适配桌面和移动端，随时随地管理书签。
- **搜索功能**：快速搜索书签名称、URL、分类或备注。

## 主要功能

1. **书签管理**：
   - 添加、编辑、删除书签。
   - 支持分类管理（新建、修改、删除分类）。
   - 可添加备注，方便记录额外信息。

2. **图标自动抓取**：
   - 优先检查默认 `favicon.ico`。
   - 解析 HTML 中的 `<link>` 标签（如 `rel="icon"` 或 `apple-touch-icon`）。
   - 支持 Web App Manifest 和 Microsoft `browserconfig.xml`。
   - 使用 CORS 代理（如 `api.allorigins.win`）确保跨域抓取成功。
   - 回退到 Google FaviconV2 服务。

3. **用户体验**：
   - 拖拽排序书签，实时保存顺序。
   - 右键或长按书签显示编辑/删除选项。
   - 动态调整书签文本大小，确保显示完整。

4. **视觉设计**：
   - 渐变背景（明暗主题可选）。
   - 半透明容器和阴影效果。
   - 樱花飘落动画，提升趣味性。

## 部署要求

- **PHP**: 7.4 或更高版本（推荐 8.x）。
- **MySQL**: 5.6 或更高版本。
- **Web 服务器**: 任意支持 PHP 的服务器（如 Apache、Nginx）。
- **空间**: 至少 1MB（包括源代码和数据库）。

## 部署教程

### 1. 下载源代码

### 2. 上传到虚拟主机
```
public_html/
  ├── add.php
  ├── config.php
  ├── delete.php
  ├── delete_category.php
  ├── edit.php
  ├── edit_category.php
  ├── index.php
  ├── install.php
  ├── login.php
  ├── script.js
  ├── style.css
  ├── images/
  │   ├── default-bookmark.png
  │   └── favicon.ico
```
2. 上传：
   - 使用 FTP 工具（如 FileZilla）将整个文件夹上传到虚拟主机的 public_html 或指定目录。
   - 例如，上传到 /public_html/

3. 设置权限（可选）：
   - 确保 config.php 和 images/ 文件夹可读（权限通常为 644 或 755）。

### 3. 访问站点
- 在浏览器中访问：
  http://yourdomain.com/
- 填写配置数据库信息，绑定数据库
- 登录后即可开始添加和管理书签！

## 使用说明

1. 添加书签：
   - 点击“+”按钮，填写名称、URL、分类等信息。
   - 图标字段留空会自动抓取网页图标。

2. 编辑/删除：
   - 右键（电脑）或长按（手机）书签，点击“编辑”或“删除”。

3. 分类管理：
   - 在分类标题旁点击“✏️”编辑，或“🗑️”删除整个分类。

4. 排序：
   - 拖动书签调整顺序，松手后自动保存。

5. 搜索：
   - 在搜索框输入关键词，按 Enter 或点击放大镜搜索。

6. 主题切换：
   - 点击“切换主题”按钮，切换明暗模式。

## 注意事项

- 安全性：
  - 建议将 config.php 中的数据库密码和站点密码设置为强密码。
  - 部署到公网时，启用 HTTPS 以保护数据传输。

- 图标抓取：
  - 依赖网络连接，可能受浏览器 CORS 限制影响。
  - 如果抓取失败，会使用默认图标 images/default-bookmark.png。

- 虚拟主机限制：
  - 确保主机支持 PHP session
## 贡献

欢迎提交 Issues 或 Pull Requests！如果有新功能建议或 Bug 反馈，请随时联系。

## 致谢

特别感谢 xAI 的 Grok 在开发过程中提供的代码支持和调试帮助。

## 许可证

MIT License - 自由使用、修改和分发。
