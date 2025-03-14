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

// 拖拽排序
const categories = document.querySelectorAll('.category');
categories.forEach(category => {
    const container = category.querySelector('.bookmark-container');
    const bookmarks = container.querySelectorAll('.bookmark');
    let draggedItem = null;

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

        bookmark.addEventListener('dragover', e => e.preventDefault());
        bookmark.addEventListener('dragenter', e => e.preventDefault());

        bookmark.addEventListener('drop', function() {
            if (draggedItem !== this) {
                const allItems = [...container.querySelectorAll('.bookmark')];
                const draggedIndex = allItems.indexOf(draggedItem);
                const targetIndex = allItems.indexOf(this);
                if (draggedIndex < targetIndex) {
                    this.after(draggedItem);
                } else {
                    this.before(draggedItem);
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

        // 初始化时调整文本显示
        adjustTextDisplay(bookmark);
    });
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.bookmark')) {
        document.querySelectorAll('.bookmark').forEach(b => b.classList.remove('active'));
    }
});

// 改进的文本显示调整函数
function adjustTextDisplay(bookmark) {
    const textContainer = bookmark.querySelector('.bookmark-text');
    const name = bookmark.querySelector('.name');
    const note = bookmark.querySelector('.note');
    
    // 设置文本容器样式
    if (textContainer) {
        textContainer.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        textContainer.style.padding = window.innerWidth <= 600 ? '3px' : '5px';
        textContainer.style.borderRadius = '5px';
        textContainer.style.maxWidth = window.innerWidth <= 600 ? '80%' : '85%';
    }
    
    // 设置文本颜色
    if (name) {
        name.style.color = '#ffffff';
        name.style.textShadow = '0 0 3px rgba(0, 0, 0, 0.8)';
        name.style.display = 'block';
    }
    
    if (note) {
        note.style.color = '#ffffff';
        note.style.textShadow = '0 0 3px rgba(0, 0, 0, 0.8)';
        note.style.display = 'block';
    }

    // 动态调整字体大小以适应容器
    if (name) {
        let fontSize = window.innerWidth <= 600 ? 0.6 : 0.73; // 手机端初始更小
        name.style.fontSize = fontSize + 'em';
        while (name.scrollWidth > textContainer.offsetWidth && fontSize > 0.33) {
            fontSize -= 0.07;
            name.style.fontSize = fontSize + 'em';
        }
    }
    
    if (note) {
        let fontSize = window.innerWidth <= 600 ? 0.5 : 0.6; // 手机端初始更小
        note.style.fontSize = fontSize + 'em';
        while (note.scrollWidth > textContainer.offsetWidth && fontSize > 0.27) {
            fontSize -= 0.07;
            note.style.fontSize = fontSize + 'em';
        }
    }
}

function saveOrder() {
    const order = {};
    document.querySelectorAll('.bookmark').forEach((bookmark, index) => {
        order[index] = bookmark.dataset.id;
    });

    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order=' + encodeURIComponent(JSON.stringify(order))
    }).then(response => response.text()).then(text => {
        if (text === 'success') console.log('排序已保存');
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
