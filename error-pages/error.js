/* 错误页的「流血」动画（errors/*.html 共用）
   --------------------------------------------------------------------------
   逻辑与原版 404 完全一致，只把「血滴位置」从写死的数组改成读 SVG 上的
   data-drips 属性，这样 400 / 403 / 404 / 500 / 503 五个页面可以共用这一份脚本，
   而不是各自复制一遍 200 行代码。

   data-drips 的格式：一串 "x,y"，用空格分隔，坐标就是 SVG 用户坐标：
       <svg id="scene" data-drips="60,140 68,140 76,140 ...">

   一滴血的生命周期：
   1. waiting   静默，随机延迟
   2. growing   从数字底部渗出，血痕逐渐变长，末端凝聚血滴
   3. detaching 血滴挣脱，血痕快速缩回，留下一个小血珠
   4. 血滴自由落体，离开画面后消失
   ========================================================================== */
(function () {
  'use strict';

  var SVG_NS = 'http://www.w3.org/2000/svg';
  var scene = document.getElementById('scene');
  var dripsLayer = document.getElementById('drips');
  if (!scene || !dripsLayer) {
    return;
  }

  /* 系统里开了「减少动态效果」就不放血：数字照常显示，只是不动 */
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  class Drip {
    constructor(x, yTop) {
      this.x = x;
      this.yTop = yTop;

      // —— 随机参数，让每一滴都不同 ——
      this.maxLen     = 25 + Math.random() * 75;     // 血痕最大长度
      this.growDur    = 2000 + Math.random() * 3000; // 生长耗时
      this.maxDropR   = 1.6 + Math.random() * 1.5;   // 末端血滴半径
      this.startDelay = Math.random() * 7000;
      this.wobbleAmp  = Math.random() * 1.6;
      this.wobblePh   = Math.random() * Math.PI * 2;
      this.color = ['#9c0a0a', '#8a0505', '#b01010', '#7a0404'][
        Math.floor(Math.random() * 4)
      ];

      // —— 元素 ——
      this.g = document.createElementNS(SVG_NS, 'g');

      this.stream = document.createElementNS(SVG_NS, 'path');
      this.stream.setAttribute('fill', this.color);
      this.stream.setAttribute('stroke', 'none');

      this.bead = document.createElementNS(SVG_NS, 'ellipse');
      this.bead.setAttribute('fill', this.color);
      this.bead.setAttribute('rx', 0);
      this.bead.setAttribute('ry', 0);

      this.falling = document.createElementNS(SVG_NS, 'ellipse');
      this.falling.setAttribute('fill', this.color);
      this.falling.setAttribute('rx', 0);
      this.falling.setAttribute('ry', 0);

      this.g.appendChild(this.stream);
      this.g.appendChild(this.bead);
      this.g.appendChild(this.falling);
      dripsLayer.appendChild(this.g);

      // —— 状态机 ——
      this.state      = 'waiting';
      this.stateStart = performance.now();
      this.fallingActive = false;
      this.fallVy = 0;
      this.fallY  = 0;

      this.lastT = performance.now();
      this.tick = this.tick.bind(this);
      requestAnimationFrame(this.tick);
    }

    /* 用三次贝塞尔画出两侧微弯的血痕 */
    setStream(wTop, wBot, len, dx) {
      const x  = this.x;
      const y1 = this.yTop;
      const y2 = this.yTop + len;

      this.stream.setAttribute('d', `
      M ${x - wTop / 2} ${y1}
      L ${x + wTop / 2} ${y1}
      C ${x + wTop / 2} ${y1 + len * 0.4},
        ${x + dx + wBot / 2} ${y1 + len * 0.7},
        ${x + dx + wBot / 2} ${y2}
      L ${x + dx - wBot / 2} ${y2}
      C ${x + dx - wBot / 2} ${y1 + len * 0.7},
        ${x - wTop / 2} ${y1 + len * 0.4},
        ${x - wTop / 2} ${y1}
      Z
    `);
    }

    tick(now) {
      const dt = Math.min(50, now - this.lastT);
      this.lastT = now;

      /* ---------- 先处理自由落体中的血滴 ---------- */
      if (this.fallingActive) {
        this.fallVy += 0.22;          // 重力
        this.fallY  += this.fallVy;

        const stretch = 1 + Math.min(1.6, this.fallVy * 0.05);
        this.falling.setAttribute('cy', this.fallY);
        this.falling.setAttribute('ry', this.maxDropR * 1.2 * stretch);

        if (this.fallY > 350) {
          this.fallingActive = false;
          this.falling.setAttribute('rx', 0);
          this.falling.setAttribute('ry', 0);
        }
      }

      const elapsed = now - this.stateStart;

      /* ---------- 状态：等待 ---------- */
      if (this.state === 'waiting') {
        if (elapsed > this.startDelay) {
          this.state = 'growing';
          this.stateStart = now;
        }
      }

      /* ---------- 状态：血痕生长 ---------- */
      else if (this.state === 'growing') {
        const prog  = Math.min(1, elapsed / this.growDur);
        const eased = 1 - Math.pow(1 - prog, 1.8);   // ease-out
        const len   = eased * this.maxLen;

        const dx = Math.sin(elapsed * 0.002 + this.wobblePh)
                 * this.wobbleAmp * prog;

        this.setStream(2.6, 1.8, len, dx);

        /* 末端血滴随血痕一起长大 */
        const dropR = this.maxDropR * Math.min(1, eased * 1.4);
        this.bead.setAttribute('cx', this.x + dx);
        this.bead.setAttribute('cy', this.yTop + len);
        this.bead.setAttribute('rx', dropR * 0.9);
        this.bead.setAttribute('ry', dropR * 1.2);

        /* 血滴够大了 —— 挣脱！ */
        if (prog >= 1) {
          this.state = 'detaching';
          this.stateStart = now;
          this.detachLen = len;
          this.detachDx  = dx;

          this.fallingActive = true;
          this.fallY  = this.yTop + len;
          this.fallVy = 0.4;
          this.falling.setAttribute('cx', this.x + dx);
          this.falling.setAttribute('cy', this.fallY);
          this.falling.setAttribute('rx', dropR * 0.9);
          this.falling.setAttribute('ry', dropR * 1.2);

          this.bead.setAttribute('rx', 0);
          this.bead.setAttribute('ry', 0);
        }
      }

      /* ---------- 状态：血痕缩回 ---------- */
      else if (this.state === 'detaching') {
        const prog = Math.min(1, elapsed / 260);
        const len  = this.detachLen * (1 - prog);
        const dx   = this.detachDx  * (1 - prog);
        const w    = 1 - prog;

        if (len > 0.5) {
          this.setStream(2.6 * w, 1.8 * w, len, dx);
        } else {
          this.stream.setAttribute('d', '');
        }

        /* 残留的小血珠 */
        const beadR = this.maxDropR * 0.7 * (1 - prog);
        this.bead.setAttribute('cx', this.x + dx);
        this.bead.setAttribute('cy', this.yTop + len);
        this.bead.setAttribute('rx', beadR);
        this.bead.setAttribute('ry', beadR);

        if (prog >= 1) {
          this.state = 'waiting';
          this.stateStart = now;
          this.startDelay = 2500 + Math.random() * 6000;

          /* 重新随机，下一滴血会不一样 */
          this.maxLen   = 25 + Math.random() * 75;
          this.growDur  = 2000 + Math.random() * 3000;
          this.maxDropR = 1.6 + Math.random() * 1.5;
          this.wobbleAmp = Math.random() * 1.6;
          this.wobblePh  = Math.random() * Math.PI * 2;

          this.bead.setAttribute('rx', 0);
          this.bead.setAttribute('ry', 0);
        }
      }

      requestAnimationFrame(this.tick);
    }
  }

  /* 在数字底部布置血滴 —— 位置由页面上的 data-drips 提供 */
  var spec = (scene.getAttribute('data-drips') || '').trim();
  if (spec === '') {
    return;
  }

  spec.split(/\s+/).forEach(function (pair) {
    var parts = pair.split(',');
    if (parts.length < 2) {
      return;
    }
    var x = parseFloat(parts[0]);
    var y = parseFloat(parts[1]);
    if (isFinite(x) && isFinite(y)) {
      new Drip(x, y);
    }
  });
})();
