(function () {
    "use strict";

    var STYLE_SELECTOR = 'html[data-style="tech"]';
    var CANVAS_ID = 'tech-bg-canvas';
    var BLESSINGS = [
        '愿你每一次早起，都离梦想更近一步。',
        '祝你考场上思路如钱江潮涌，下笔有神。',
        '愿你的努力不被辜负，所有辛苦都变成成绩单上的惊喜。',
        '祝你拥有一个不后悔的初中，哪怕有泪，也是甜的。',
        '愿你身边总有能借你半块橡皮的人，也有陪你疯闹的人。',
        '祝你在每一个困倦的早晨，都能被食堂的香气温柔叫醒。',
        '愿你面对难题时，有拆解它的勇气，也有跳过它的智慧。',
        '祝你跑完八百米后，还能笑着和同学击掌。',
        '愿你记住的不是分数，而是教室里那一阵无端的笑。',
        '祝你笔下生风，心中藏海，考出自己最真的水平。',
        '愿你像西湖的荷花一样，淤泥里扎根，却开出干净的花。',
        '祝你每段友情都不散场，即使毕业，也常联系。',
        '愿你在最累的初三，也能找到让自己开心的五分钟。',
        '祝你体育中考那天，风刚好，阳光不燥，跳绳不绊。',
        '愿你背过的每一句古文，都变成你气质里的风骨。',
        '祝你遇到严格的老师，也遇到温暖的老师，都是礼物。',
        '愿你熬夜复习时，桌边总有一杯温热的牛奶。',
        '祝你答完所有题，还有时间检查，刚刚好。',
        '愿你在这个年纪，敢喜欢，敢讨厌，敢做自己。',
        '祝你每次大考后，都能理直气壮地睡到自然醒。',
        '愿你的初中记忆，有走廊的夕阳，有操场的风。',
        '祝你学到的知识，不仅用于考试，更用于看懂这个世界。',
        '愿你烦躁时有人听你吐槽，得意时有人泼你冷水。',
        '祝你数学最后一道题，刚好是你昨晚梦到的方法。',
        '愿你在浙江的雨季里，心里永远有一小块晴天。',
        '祝你英语听力时，杂音最少，心跳最稳。',
        '愿你每个周五下午，都像放了学的云一样轻盈。',
        '祝你实验操作时手不抖，数据刚刚好。',
        '愿你和你同桌，吵过架也和好过，青春没白过。',
        '祝你写作文时，灵感比窗外的蝉鸣还热闹。',
        '愿你跑操偷懒时，永远不被体育老师发现。',
        '祝你假期作业能提前写完，留两天纯粹地玩。',
        '愿你迷茫时，抬头看看浙江的星空，总能找到方向。',
        '祝你模拟考一次比一次稳，心态一次比一次松。',
        '愿你收到的每句“加油”，都真的进到了心里。',
        '祝你毕业照那天，笑得最自然，眼睛里有光。',
        '愿你所有的小纸条，都承载着善意和秘密。',
        '祝你科学推断时，像侦探一样冷静又聪明。',
        '愿你在课间十分钟里，也能收获一整天的快乐。',
        '祝你面对父母唠叨时，能耐心听完，也让他们听你说。',
        '愿你像浙江的茶树，经过修剪，反而长得更茂盛。',
        '祝你每次早起背书，都能记住重点，忘掉困意。',
        '愿你讨厌的科目，最后也变成你的勋章。',
        '祝你春游秋游时，天气永远晴好，零食永远带够。',
        '愿你犯错时有人指正，改正后有人鼓掌。',
        '祝你体育课自由活动，总能抢到最喜欢的球。',
        '愿你心里的小秘密，藏得住，也值得被珍藏。',
        '祝你刷题刷到头晕时，抬头就能看到窗外的绿。',
        '愿你在这个年纪，敢做梦，也敢为梦早起。',
        '祝你每次换座位，都遇到好相处的邻居。',
        '愿你初中三年，不只有分数，还有晚霞和歌。',
        '祝你月考后看排名，进得开心，退得坦然。',
        '愿你被老师点名回答时，刚好知道答案。',
        '祝你所有的不开心，都能被一顿校门口的小吃治愈。',
        '愿你像乌镇的水一样，看似柔软，却有自己的方向。',
        '祝你考前不失眠，考后不懊悔。',
        '愿你有个能一起去食堂抢饭的固定搭档。',
        '祝你写错题本时，觉得每一道错题都是长进。',
        '愿你心里装得下浙大的梦，也装得下当下的懒觉。',
        '祝你在这个年纪，哭就痛快哭，笑就大声笑。',
        '愿你每次大扫除，都能偷懒成功，还不被扣分。',
        '祝你听写全对，默写满分，小测验也顺顺利利。',
        '愿你讨厌的夏天，因为某个傍晚的风而原谅一切。',
        '祝你拿到录取通知书时，觉得所有熬夜都值了。',
        '愿你身边的朋友，见过你最糗的样子，还愿意站在你身边。',
        '祝你地理生物会考，轻轻松松过关。',
        '愿你像钱塘江的潮水，有起有落，但总在前进。',
        '祝你最怕的那门课，最后变成你的拉分项。',
        '愿你中午趴桌睡觉时，梦到的是美好，不是公式。',
        '祝你跑完中考体育，全身酸痛，心里却痛快。',
        '愿你写日记时，记下的都是值得回味的小事。',
        '祝你以后想起初中，嘴角是向上翘的。',
        '愿你所有临时抱佛脚，都能抱得刚刚好。',
        '祝你换新同桌时，第一天就能聊到一起。',
        '愿你像绍兴的老酒，越沉淀，越有味道。',
        '祝你考试那几天，胃口好，睡眠足，状态佳。',
        '愿你暗恋的人，正好也偷偷看着你。',
        '祝你无论如何，都别忘了自己才十几岁。',
        '愿你每个周一早上升旗时，心里都有新的期待。',
        '祝你做选择题时，第一感觉总是对的。',
        '愿你被误解时，有人站出来替你说话。',
        '祝你放暑假前，已经订好了想去的远方。',
        '愿你像浙东的竹海，风来就弯腰，风过就挺直。',
        '祝你家长会那晚，爸妈带回来的不是批评，而是烤串。',
        '愿你每天都有五分钟，纯粹发呆，不想学习。',
        '祝你毕业时，把舍不得的人都好好道个别。',
        '愿你所有的小遗憾，最后都变成后来的笑谈。',
        '祝你拿到成绩单时，先抱抱自己，辛苦了。',
        '愿你初中三年，养成了至少一个好习惯。',
        '祝你未来无论去哪所高中，都能遇到好玩的人。',
        '愿你像宁波港的船，不怕风浪，就怕不动。',
        '祝你写英语作文时，高级词汇自己跳出来。',
        '愿你体育课下雨，正好可以在室内下棋聊天。',
        '祝你所有的努力，都在某个时刻悄悄回报你。',
        '愿你记住老师的口头禅，那是青春的背景音。',
        '祝你在这个年纪，爱自己，爱世界，爱一切可能。',
        '愿你走出考场时，有战士收刀入鞘的骄傲。',
        '祝你往后人生里，初中这份单纯永远在心里。',
        '愿你一切顺利，如果不顺，也有勇气重来。',
        '祝你初中毕业那天，觉得自己真的长大了。',
        '愿这个世界，能多一些包容。'
    ];

    var canvas = null;
    var ctx = null;
    var stars = [];
    var meteors = [];
    var active = false;
    var reducedMotion = false;
    var rafId = 0;
    var running = false;
    var width = 0;
    var height = 0;
    var dpr = 1;
    var elapsed = 0;
    var lastFrame = 0;
    var nextSpawnAt = 0.1;
    var maxMeteors = 1;
    var meteorColor = '#14c8d8';
    var accentColor = '#0d8fc4';
    var starColor = '#5e7d94';
    var styleObserver = null;
    var pointerX = null;
    var pointerY = null;
    var pointerActive = false;
    var bubble = null;
    var hoveredMeteor = null;
    var hoveredBlessing = '';
    var lastBlessingIndex = -1;

    function isTechStyle() {
        return document.documentElement.matches(STYLE_SELECTOR);
    }

    function getCssVar(name, fallback) {
        var value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    }

    function toRgba(color, alpha) {
        if (!color || color[0] !== '#') {
            return 'rgba(20, 200, 216, ' + alpha + ')';
        }
        var hex = color.slice(1);
        if (hex.length === 3) {
            hex = hex.split('').map(function (c) { return c + c; }).join('');
        }
        if (hex.length !== 6) {
            return 'rgba(20, 200, 216, ' + alpha + ')';
        }
        var r = parseInt(hex.slice(0, 2), 16);
        var g = parseInt(hex.slice(2, 4), 16);
        var b = parseInt(hex.slice(4, 6), 16);
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    function randomBetween(min, max) {
        return min + Math.random() * (max - min);
    }

    function getMeteorTail(meteor) {
        var speed = Math.hypot(meteor.vx, meteor.vy) || 1;
        return {
            x: meteor.x - meteor.vx * (meteor.tail / speed),
            y: meteor.y - meteor.vy * (meteor.tail / speed)
        };
    }

    function pointToSegmentDistance(px, py, x1, y1, x2, y2) {
        var dx = x2 - x1;
        var dy = y2 - y1;
        var lengthSq = dx * dx + dy * dy;
        if (lengthSq === 0) {
            return Math.hypot(px - x1, py - y1);
        }
        var t = ((px - x1) * dx + (py - y1) * dy) / lengthSq;
        t = Math.max(0, Math.min(1, t));
        return Math.hypot(px - (x1 + t * dx), py - (y1 + t * dy));
    }

    function randomBlessing() {
        if (!BLESSINGS.length) {
            return '';
        }
        var index = Math.floor(Math.random() * BLESSINGS.length);
        if (BLESSINGS.length > 1 && index === lastBlessingIndex) {
            index = (index + 1) % BLESSINGS.length;
        }
        lastBlessingIndex = index;
        return BLESSINGS[index];
    }

    function hideBubble() {
        hoveredMeteor = null;
        hoveredBlessing = '';
        if (!bubble) {
            return;
        }
        if (bubble.classList.contains('visible')) {
            bubble.classList.remove('visible');
        }
        bubble.classList.remove('below');
    }

    function showBubble(meteor) {
        if (!bubble) {
            return;
        }
        if (bubble.textContent !== hoveredBlessing) {
            bubble.textContent = hoveredBlessing;
        }
        var below = meteor.y < 90;
        bubble.classList.toggle('below', below);
        var left = Math.min(Math.max(meteor.x + 14, 10), Math.max(10, width - 10));
        var top = Math.min(Math.max(meteor.y + (below ? 18 : -18), 10), Math.max(10, height - 10));
        bubble.style.left = left + 'px';
        bubble.style.top = top + 'px';
        if (!bubble.classList.contains('visible')) {
            bubble.classList.add('visible');
        }
    }

    function updateHover() {
        if (!bubble || !pointerActive) {
            hideBubble();
            return;
        }
        var best = null;
        var bestDistance = Infinity;
        for (var i = 0; i < meteors.length; i += 1) {
            var meteor = meteors[i];
            if (meteor.opacity <= 0.02) {
                continue;
            }
            var tail = getMeteorTail(meteor);
            var distance = pointToSegmentDistance(
                pointerX,
                pointerY,
                tail.x,
                tail.y,
                meteor.x,
                meteor.y
            );
            if (distance <= 24 && distance < bestDistance) {
                bestDistance = distance;
                best = meteor;
            }
        }
        if (best !== hoveredMeteor) {
            hoveredMeteor = best;
            hoveredBlessing = best ? randomBlessing() : '';
        }
        if (hoveredMeteor) {
            showBubble(hoveredMeteor);
        } else {
            hideBubble();
        }
    }

    function handlePointerMove(event) {
        pointerActive = true;
        pointerX = event.clientX;
        pointerY = event.clientY;
        updateHover();
    }

    function handlePointerLeave() {
        pointerActive = false;
        pointerX = null;
        pointerY = null;
        updateHover();
    }

    function makeStar() {
        return {
            x: Math.random() * width,
            y: Math.random() * height,
            r: randomBetween(0.4, 1.7),
            alpha: randomBetween(0.18, 0.72),
            speed: randomBetween(0.4, 2.1),
            phase: Math.random() * Math.PI * 2
        };
    }

    function makeMeteor() {
        var fromRight = Math.random() < 0.5;
        var speed = randomBetween(240, 420);//340, 620
        var slope = 1 / Math.hypot(0.55, 1);
        var vx = -speed * 0.55 * slope;
        var vy = speed * slope;
        var tailLength = randomBetween(90, 240);
        var meteor = {
            x: 0,
            y: 0,
            vx: vx,
            vy: vy,
            tail: tailLength,
            life: 0,
            lifeMax: randomBetween(3.2, 4.9),
            radius: randomBetween(1.2, 2.4),
            opacity: 1
        };

        if (fromRight) {
            meteor.x = width + randomBetween(0, Math.min(220, width * 0.18));
            meteor.y = randomBetween(-height * 0.1, height * 0.45);
        } else {
            meteor.x = randomBetween(-width * 0.08, width * 0.9);
            meteor.y = -randomBetween(0, Math.min(180, height * 0.2));
        }
        return meteor;
    }

    function updatePalette() {
        meteorColor = getCssVar('--tech-cyan', '#14c8d8');
        accentColor = getCssVar('--tech-accent', '#0d8fc4');
        starColor = getCssVar('--tech-muted', '#5e7d94');
    }

    function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        width = window.innerWidth;
        height = window.innerHeight;
        maxMeteors = Math.max(6, Math.min(18, Math.round(width / 90)));
        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(height * dpr);
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        updatePalette();
        buildStars();
    }

    function buildStars() {
        var count = Math.max(50, Math.min(180, Math.round((width * height) / 11000)));
        stars = [];
        for (var i = 0; i < count; i += 1) {
            stars.push(makeStar());
        }
    }

    function drawStars(now) {
        ctx.save();
        for (var i = 0; i < stars.length; i += 1) {
            var star = stars[i];
            var twinkle = reducedMotion
                ? 1
                : 0.62 + 0.38 * Math.sin(now * 0.001 * star.speed + star.phase);
            ctx.beginPath();
            ctx.arc(star.x, star.y, star.r, 0, Math.PI * 2);
            ctx.fillStyle = toRgba(starColor, star.alpha * twinkle);
            ctx.fill();
        }
        ctx.restore();
    }

    function drawMeteor(meteor, now) {
        var fadeIn = Math.min(1, meteor.life / 0.32);
        var fadeOut = Math.min(1, (meteor.lifeMax - meteor.life) / 0.55);
        meteor.opacity = Math.max(0, Math.min(fadeIn, fadeOut));
        if (meteor.opacity <= 0.01) {
            return;
        }

        var speed = Math.hypot(meteor.vx, meteor.vy);
        var tailX = meteor.x - meteor.vx * (meteor.tail / speed);
        var tailY = meteor.y - meteor.vy * (meteor.tail / speed);
        var lineWidth = Math.max(1.4, meteor.radius * 1.6);
        var gradient = ctx.createLinearGradient(tailX, tailY, meteor.x, meteor.y);

        gradient.addColorStop(0, toRgba(meteorColor, 0));
        gradient.addColorStop(0.45, toRgba(meteorColor, meteor.opacity * 0.22));
        gradient.addColorStop(1, toRgba(meteorColor, meteor.opacity * 0.92));

        ctx.save();
        ctx.lineCap = 'round';
        ctx.lineWidth = lineWidth;
        ctx.strokeStyle = gradient;
        ctx.shadowColor = toRgba(meteorColor, meteor.opacity * 0.75);
        ctx.shadowBlur = 14 * meteor.opacity;
        ctx.beginPath();
        ctx.moveTo(tailX, tailY);
        ctx.lineTo(meteor.x, meteor.y);
        ctx.stroke();

        ctx.shadowBlur = 20 * meteor.opacity;
        ctx.fillStyle = toRgba(accentColor, meteor.opacity * 0.95);
        ctx.beginPath();
        ctx.arc(meteor.x, meteor.y, meteor.radius, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    function frame(now) {
        if (!running) {
            return;
        }
        if (!lastFrame) {
            lastFrame = now;
            rafId = requestAnimationFrame(frame);
            return;
        }
        var dt = Math.min((now - lastFrame) / 1000, 0.05);
        lastFrame = now;
        elapsed += dt;

        if (!reducedMotion && meteors.length < maxMeteors && elapsed >= nextSpawnAt) {
            meteors.push(makeMeteor());
            nextSpawnAt = elapsed + randomBetween(0.02, 0.12);
        }

        ctx.clearRect(0, 0, width, height);
        drawStars(now);

        for (var i = meteors.length - 1; i >= 0; i -= 1) {
            var meteor = meteors[i];
            meteor.life += dt;
            meteor.x += meteor.vx * dt;
            meteor.y += meteor.vy * dt;
            drawMeteor(meteor, now);
            if (
                meteor.life >= meteor.lifeMax ||
                meteor.x < -meteor.tail ||
                meteor.y > height + meteor.tail
            ) {
                meteors.splice(i, 1);
            }
        }

        updateHover();

        rafId = requestAnimationFrame(frame);
    }

    function start() {
        if (running || !canvas || !isTechStyle()) {
            return;
        }
        running = true;
        lastFrame = 0;
        elapsed = 0;
        nextSpawnAt = 0.1;
        meteors = [];
        hideBubble();
        resize();
        if (reducedMotion) {
            ctx.clearRect(0, 0, width, height);
            drawStars(0);
            return;
        }
        rafId = requestAnimationFrame(frame);
    }

    function stop() {
        running = false;
        cancelAnimationFrame(rafId);
        rafId = 0;
        hideBubble();
        if (canvas) {
            ctx.clearRect(0, 0, width, height);
        }
    }

    function sync() {
        if (isTechStyle()) {
            if (!canvas) {
                setup();
            } else {
                canvas.style.display = 'block';
                updatePalette();
            }
            start();
        } else {
            stop();
            if (canvas) {
                canvas.style.display = 'none';
            }
        }
    }

    function setup() {
        canvas = document.getElementById(CANVAS_ID);
        if (!canvas) {
            canvas = document.createElement('canvas');
            canvas.id = CANVAS_ID;
            canvas.setAttribute('aria-hidden', 'true');
            document.body.appendChild(canvas);
        }
        ctx = canvas.getContext('2d');

        reducedMotion = window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        setupWatcher();

        bubble = document.createElement('div');
        bubble.id = 'tech-meteor-blessing';
        bubble.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bubble);

        window.addEventListener('resize', debounce(resize));
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stop();
            } else {
                sync();
            }
        });
        document.addEventListener('pointermove', handlePointerMove);
        document.addEventListener('pointerleave', handlePointerLeave);
        window.addEventListener('blur', handlePointerLeave);
    }

    function setupWatcher() {
        if (styleObserver) {
            return;
        }
        styleObserver = new MutationObserver(function () {
            sync();
        });
        styleObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-style', 'data-theme']
        });
    }

    function debounce(fn) {
        var timer = 0;
        return function () {
            clearTimeout(timer);
            timer = setTimeout(fn, 120);
        };
    }

    setupWatcher();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sync);
    } else {
        sync();
    }
})();
