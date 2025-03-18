// 搜索功能
document.getElementById('search').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        searchBookmarks();
    }
});

function searchBookmarks() {
    const searchValue = document.getElementById('search').value;
    window.location.href = 'index.php?search=' + encodeURIComponent(searchValue);
}

// 分类导航高亮
const navLinks = document.querySelectorAll('.category-nav a');
navLinks.forEach(link => {
    link.addEventListener('click', function() {
        navLinks.forEach(l => l.classList.remove('active'));
        this.classList.add('active');
    });
});

// 拖拽排序（支持跨分类）
const containers = document.querySelectorAll('.bookmark-container');
let draggedItem = null;

containers.forEach(container => {
    const bookmarks = container.querySelectorAll('.bookmark');

    bookmarks.forEach(bookmark => {
        bookmark.draggable = true;

        bookmark.addEventListener('dragstart', () => {
            draggedItem = bookmark;
            setTimeout(() => bookmark.style.opacity = '0.5', 0);
        });

        bookmark.addEventListener('dragend', () => {
            setTimeout(() => {
                draggedItem.style.opacity = '1';
                draggedItem = null;
                saveOrder();
            }, 0);
        });

        container.addEventListener('dragover', e => e.preventDefault());
        container.addEventListener('dragenter', e => e.preventDefault());

        container.addEventListener('drop', function(e) {
            e.preventDefault();
            if (draggedItem && draggedItem !== this) {
                const allItems = [...this.querySelectorAll('.bookmark')];
                const closestBookmark = getClosestBookmark(e.clientX, e.clientY, allItems);
                if (closestBookmark) {
                    const draggedIndex = allItems.indexOf(draggedItem);
                    const targetIndex = allItems.indexOf(closestBookmark);
                    if (draggedIndex === -1) {
                        if (targetIndex === 0) {
                            this.insertBefore(draggedItem, closestBookmark);
                        } else {
                            closestBookmark.after(draggedItem);
                        }
                    }
                } else {
                    this.appendChild(draggedItem);
                }
            }
        });

        bookmark.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            document.querySelectorAll('.bookmark').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            adjustTextDisplay(this);
        });

        let touchTimer;
        bookmark.addEventListener('touchstart', function(e) {
            touchTimer = setTimeout(() => {
                e.preventDefault();
                document.querySelectorAll('.bookmark').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                adjustTextDisplay(this);
            }, 500);
        });

        bookmark.addEventListener('touchend', () => clearTimeout(touchTimer));
        bookmark.addEventListener('touchmove', () => clearTimeout(touchTimer));

        bookmark.addEventListener('mouseleave', function() {
            this.classList.remove('active');
        });

        adjustTextDisplay(bookmark);
    });
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.bookmark')) {
        document.querySelectorAll('.bookmark').forEach(b => b.classList.remove('active'));
    }
});

function getClosestBookmark(x, y, bookmarks) {
    let closest = null;
    let minDistance = Infinity;

    bookmarks.forEach(bookmark => {
        const rect = bookmark.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        const distance = Math.sqrt((x - centerX) ** 2 + (y - centerY) ** 2);
        if (distance < minDistance) {
            minDistance = distance;
            closest = bookmark;
        }
    });

    return closest;
}

// 改进的文本显示调整函数
function adjustTextDisplay(bookmark) {
    const textContainer = bookmark.querySelector('.bookmark-text');
    const name = bookmark.querySelector('.name');
    const note = bookmark.querySelector('.note');
    
    if (textContainer) {
        textContainer.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        textContainer.style.padding = window.innerWidth <= 600 ? '3px' : '5px';
        textContainer.style.borderRadius = '5px';
        textContainer.style.width = '100%';
        textContainer.style.height = '100%';
        textContainer.style.maxWidth = '100%';
        textContainer.style.position = 'absolute';
        textContainer.style.top = '0';
        textContainer.style.left = '0';
        textContainer.style.display = 'flex';
        textContainer.style.flexDirection = 'column';
        textContainer.style.alignItems = 'center'; // 居中对齐
        textContainer.style.boxSizing = 'border-box';
        textContainer.style.gap = '1.2em'; // 名称和备注间距为一行高度
    }
    
    if (name) {
        name.style.color = '#ffffff';
        name.style.textShadow = '0 0 3px rgba(0, 0, 0, 0.8)';
        name.style.display = '-webkit-box';
        name.style.webkitBoxOrient = 'vertical';
        name.style.webkitLineClamp = '2';
        name.style.overflow = 'hidden';
        name.style.textOverflow = 'ellipsis';
        name.style.paddingTop = window.innerWidth <= 600 ? '3px' : '5px';
        name.style.lineHeight = '1.2em';
        name.style.maxHeight = '2.4em'; // 2 行高度
        let fontSize = window.innerWidth <= 600 ? 0.6 : 0.73;
        name.style.fontSize = fontSize + 'em';
        while (name.scrollWidth > textContainer.offsetWidth && fontSize > 0.33) {
            fontSize -= 0.07;
            name.style.fontSize = fontSize + 'em';
        }
    }
    
    if (note) {
        note.style.color = '#ffffff';
        note.style.textShadow = '0 0 3px rgba(0, 0, 0, 0.8)';
        note.style.display = '-webkit-box';
        note.style.webkitBoxOrient = 'vertical';
        note.style.webkitLineClamp = '3';
        note.style.overflow = 'hidden';
        note.style.textOverflow = 'ellipsis';
        note.style.lineHeight = '1.2em';
        note.style.maxHeight = '3.6em'; // 3 行高度
        let fontSize = window.innerWidth <= 600 ? 0.5 : 0.6;
        note.style.fontSize = fontSize + 'em';
        while (note.scrollWidth > textContainer.offsetWidth && fontSize > 0.27) {
            fontSize -= 0.07;
            note.style.fontSize = fontSize + 'em';
        }
    }
}

// 保存排序
function saveOrder() {
    const order = {};
    document.querySelectorAll('.bookmark-container').forEach(container => {
        const parent = container.parentElement;
        const category = parent.classList.contains('category') ? decodeURIComponent(parent.id) : '';
        container.querySelectorAll('.bookmark').forEach((bookmark, index) => {
            order[bookmark.dataset.id] = { position: index, category: category };
        });
    });

    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order=' + encodeURIComponent(JSON.stringify(order))
    }).then(response => response.text()).then(text => {
        if (text === 'success') {
            console.log('排序已保存');
        } else {
            console.error('保存排序失败:', text);
        }
    });
}

// 樱花特效
const canvas = document.getElementById('sakura');
const ctx = canvas.getContext('2d');
canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

window.addEventListener('resize', () => {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
});

class Sakura {
    constructor() {
        this.x = Math.random() * canvas.width;
        this.y = Math.random() * -canvas.height;
        this.size = Math.random() * 10 + 5;
        this.speedX = Math.random() * 2 - 1;
        this.speedY = Math.random() * 2 + 1;
        this.opacity = Math.random() * 0.5 + 0.5;
    }

    draw() {
        ctx.fillStyle = `rgba(255, 105, 180, ${this.opacity})`;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size / 2, 0, Math.PI * 2);
        ctx.fill();
    }

    update() {
        this.x += this.speedX;
        this.y += this.speedY;
        if (this.y > canvas.height + this.size) {
            this.y = -this.size;
            this.x = Math.random() * canvas.width;
        }
    }
}

const petals = [];
for (let i = 0; i < 50; i++) {
    petals.push(new Sakura());
}

function animateSakura() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    petals.forEach(petal => {
        petal.update();
        petal.draw();
    });
    requestAnimationFrame(animateSakura);
}

animateSakura();

// 主题切换
const themeToggle = document.getElementById('theme-toggle');
const body = document.body;

function setTheme(theme) {
    body.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
}

themeToggle.addEventListener('click', () => {
    const currentTheme = body.getAttribute('data-theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
});

const savedTheme = localStorage.getItem('theme') || 'dark';
setTheme(savedTheme);