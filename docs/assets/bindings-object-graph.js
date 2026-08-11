(function () {
  'use strict';

  var mount = document.getElementById('object-graph-mount');
  var raw = document.getElementById('object-graph-dot');
  var search = document.getElementById('object-graph-search');
  var count = document.getElementById('object-graph-search-count');
  var zoomIn = document.getElementById('object-graph-zoom-in');
  var zoomOut = document.getElementById('object-graph-zoom-out');
  var fullscreenBtn = document.getElementById('object-graph-fullscreen');
  var resetBtn = document.getElementById('object-graph-reset');
  if (!mount || !raw || !search || !count || !zoomIn || !zoomOut || !fullscreenBtn || !resetBtn) {
    return;
  }

  var srcmap = [];
  var mapEl = document.getElementById('srcmap');
  if (mapEl) { try { srcmap = JSON.parse(mapEl.textContent) || []; } catch (e) { srcmap = []; } }

  // Same resolution as the bindings viewer: exact-class overrides first, then
  // longest PSR-4 prefix. Only http(s) repositories become links.
  function resolve(fqcn) {
    for (var i = 0; i < srcmap.length; i++) {
      var ex = srcmap[i];
      if (ex.x === 1 && ex.p === fqcn && ex.path && /^https?:\/\//.test(ex.u)) {
        return { gh: ex.u + (ex.u.indexOf('github.com') !== -1 ? '/blob/' + ex.r + '/' + ex.path : ''), local: 'vendor/' + ex.n + '/' + ex.path };
      }
    }
    var best = null;
    for (var j = 0; j < srcmap.length; j++) {
      var e = srcmap[j];
      if (e.x === 1) { continue; }
      if (fqcn.indexOf(e.p) === 0 && (!best || e.p.length > best.p.length)) { best = e; }
    }
    if (!best || !/^https?:\/\//.test(best.u)) { return null; }
    var file = (best.d ? best.d + '/' : '') + fqcn.slice(best.p.length).replace(/\\/g, '/') + '.php';
    var gh = best.u.indexOf('github.com') !== -1 ? best.u + '/blob/' + best.r + '/' + file : best.u;
    return { gh: gh, local: 'vendor/' + best.n + '/' + file };
  }

  // Rebuild the FQCN from the node label texts (namespace, then class name);
  // stop at the first non-name row such as "<construct>".
  function nodeClassName(node) {
    var texts = Array.from(node.querySelectorAll('text'));
    var parts = [];
    for (var i = 0; i < texts.length; i++) {
      var t = (texts[i].textContent || '').trim();
      if (/^@?[A-Za-z_][A-Za-z0-9_\\]*$/.test(t)) { parts.push(t); } else { break; }
    }
    if (parts.length === 1 && parts[0].charAt(0) === '@') {
      return parts[0].slice(1);
    }
    return parts.length > 1 ? parts.join('\\') : null;
  }

  function showStatus(message) {
    var status = document.createElement('p');
    status.className = 'object-graph-status';
    status.textContent = message;
    mount.replaceChildren(status);
  }

  function sanitize(svg) {
    svg.querySelectorAll('script,foreignObject').forEach(function (element) {
      element.remove();
    });
    svg.querySelectorAll('*').forEach(function (element) {
      Array.from(element.attributes).forEach(function (attribute) {
        var value = attribute.value.trim().toLowerCase();
        if (attribute.name.toLowerCase().startsWith('on') || value.startsWith('javascript:')) {
          element.removeAttribute(attribute.name);
        }
      });
    });
  }

  function enableSearch(svg) {
    var autoMaxScale = 1.5;
    var manualMaxScale = 4;
    var originalViewBox = svg.getAttribute('viewBox');
    var originalBounds = originalViewBox ? originalViewBox.split(/[,\s]+/).map(Number) : null;
    var nodes = Array.from(svg.querySelectorAll('g.node'));
    var edges = Array.from(svg.querySelectorAll('g.edge'));
    var searchable = nodes.map(function (node) {
      var labels = Array.from(node.querySelectorAll('text')).map(function (label) {
        return label.textContent || '';
      });

      return {element: node, text: labels.join('\\').toLowerCase()};
    });

    function matches(text, terms) {
      return terms.every(function (term) {
        return text.includes(term);
      });
    }

    function filterRows(selector, terms) {
      var elements = Array.from(document.querySelectorAll(selector));
      var matchesCount = 0;
      elements.forEach(function (element) {
        var match = terms.length === 0 || matches((element.textContent || '').toLowerCase(), terms);
        element.classList.toggle('is-search-hidden', !match);
        matchesCount += match ? 1 : 0;
      });

      return matchesCount;
    }

    function recordView() {
      var v = svg.viewBox.baseVal;
      var parts = [v.x, v.y, v.width, v.height].map(function (n) { return Math.round(n * 10) / 10; });
      history.replaceState(null, '', '#graph=' + parts.join(','));
    }

    function clearView() {
      history.replaceState(null, '', location.pathname + location.search);
    }

    function resetView() {
      if (originalViewBox) {
        svg.setAttribute('viewBox', originalViewBox);
      }
      mount.classList.remove('is-focused');
      mount.title = '';
      clearView();
    }

    function zoomBy(factor, px, py) {
      px = px === undefined ? 0.5 : px;
      py = py === undefined ? 0.5 : py;
      var viewBox = svg.viewBox.baseVal;
      if (factor < 1) {
        factor = Math.max(
          factor,
          mount.clientWidth / manualMaxScale / viewBox.width,
          mount.clientHeight / manualMaxScale / viewBox.height
        );
        if (factor >= 1) {
          return;
        }
      }
      if (originalBounds && factor > 1 && (
        viewBox.width * factor >= originalBounds[2]
        || viewBox.height * factor >= originalBounds[3]
      )) {
        resetView();
        return;
      }
      var width = Math.max(viewBox.width * factor, 1);
      var height = Math.max(viewBox.height * factor, 1);
      var anchorX = viewBox.x + viewBox.width * px;
      var anchorY = viewBox.y + viewBox.height * py;
      var x = anchorX - width * px;
      var y = anchorY - height * py;
      if (originalBounds) {
        if (width < originalBounds[2]) {
          x = Math.min(Math.max(x, originalBounds[0]), originalBounds[0] + originalBounds[2] - width);
        }
        if (height < originalBounds[3]) {
          y = Math.min(Math.max(y, originalBounds[1]), originalBounds[1] + originalBounds[3] - height);
        }
      }
      svg.setAttribute('viewBox', [x, y, width, height].join(' '));
      mount.classList.add('is-focused');
      recordView();
    }

    function nodeBounds(node) {
      var box = node.getBBox();
      var nodeMatrix = node.getCTM();
      var svgMatrix = svg.getCTM();
      if (!nodeMatrix || !svgMatrix) {
        return box;
      }
      var matrix = svgMatrix.inverse().multiply(nodeMatrix);
      var points = [
        new DOMPoint(box.x, box.y),
        new DOMPoint(box.x + box.width, box.y),
        new DOMPoint(box.x, box.y + box.height),
        new DOMPoint(box.x + box.width, box.y + box.height)
      ].map(function (point) {
        return point.matrixTransform(matrix);
      });
      var xs = points.map(function (point) { return point.x; });
      var ys = points.map(function (point) { return point.y; });
      var minX = Math.min.apply(null, xs);
      var maxX = Math.max.apply(null, xs);
      var minY = Math.min.apply(null, ys);
      var maxY = Math.max.apply(null, ys);

      return {x: minX, y: minY, width: maxX - minX, height: maxY - minY};
    }

    function focusNodes(matchingNodes) {
      if (matchingNodes.length === 0) {
        resetView();
        return;
      }
      var boxes = matchingNodes.map(nodeBounds);
      var minX = Math.min.apply(null, boxes.map(function (box) { return box.x; }));
      var minY = Math.min.apply(null, boxes.map(function (box) { return box.y; }));
      var maxX = Math.max.apply(null, boxes.map(function (box) { return box.x + box.width; }));
      var maxY = Math.max.apply(null, boxes.map(function (box) { return box.y + box.height; }));
      var width = Math.max(maxX - minX, 1);
      var height = Math.max(maxY - minY, 1);
      var padding = Math.max(Math.max(width, height) * 0.08, 12);
      minX -= padding;
      minY -= padding;
      width += padding * 2;
      height += padding * 2;

      var viewportRatio = mount.clientWidth / mount.clientHeight;
      var boxRatio = width / height;
      if (boxRatio > viewportRatio) {
        var fittedHeight = width / viewportRatio;
        minY -= (fittedHeight - height) / 2;
        height = fittedHeight;
      } else {
        var fittedWidth = height * viewportRatio;
        minX -= (fittedWidth - width) / 2;
        width = fittedWidth;
      }
      var autoMinWidth = mount.clientWidth / autoMaxScale;
      var autoMinHeight = mount.clientHeight / autoMaxScale;
      if (width < autoMinWidth || height < autoMinHeight) {
        var autoScale = Math.max(autoMinWidth / width, autoMinHeight / height);
        var centerX = minX + width / 2;
        var centerY = minY + height / 2;
        width *= autoScale;
        height *= autoScale;
        minX = centerX - width / 2;
        minY = centerY - height / 2;
      }
      if (originalBounds) {
        if (width < originalBounds[2]) {
          minX = Math.min(Math.max(minX, originalBounds[0]), originalBounds[0] + originalBounds[2] - width);
        }
        if (height < originalBounds[3]) {
          minY = Math.min(Math.max(minY, originalBounds[1]), originalBounds[1] + originalBounds[3] - height);
        }
      }
      svg.setAttribute('viewBox', [minX, minY, width, height].join(' '));
      mount.classList.add('is-focused');
      recordView();
    }

    function filter() {
      var terms = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
      var nodeMatches = 0;
      var matchingNodes = [];
      searchable.forEach(function (node) {
        var match = terms.length === 0 || matches(node.text, terms);
        node.element.classList.toggle('is-match', terms.length > 0 && match);
        node.element.classList.toggle('is-dimmed', !match);
        nodeMatches += match ? 1 : 0;
        if (terms.length > 0 && match) {
          matchingNodes.push(node.element);
        }
      });
      edges.forEach(function (edge) {
        edge.classList.toggle('is-dimmed', terms.length > 0);
      });

      var bindings = filterRows('#view .b', terms);
      var provenance = filterRows('#view .ev', terms);
      var moduleRow = document.querySelector('#view .ml');
      var modulesSection = moduleRow ? moduleRow.closest('section') : null;
      if (modulesSection) {
        modulesSection.classList.toggle('is-search-hidden', terms.length > 0);
      }
      if (terms.length === 0) {
        resetView();
      } else {
        focusNodes(matchingNodes);
      }
      count.textContent = terms.length === 0
        ? nodeMatches + ' / ' + nodes.length
        : nodeMatches + ' nodes · ' + bindings + ' bindings · ' + provenance + ' provenance';
    }

    function fitBoxHeight() {
      if (document.fullscreenElement === mount || !originalBounds) {
        return;
      }
      var ratio = originalBounds[3] / Math.max(originalBounds[2], 1);
      var needed = mount.clientWidth * ratio + 20;
      var maxHeight = window.innerHeight * 0.78;
      mount.style.height = Math.max(320, Math.min(needed, maxHeight)) + 'px';
    }

    search.disabled = false;
    zoomIn.disabled = false;
    zoomOut.disabled = false;
    resetBtn.disabled = false;
    resetBtn.addEventListener('click', function () {
      resetView();
    });
    search.addEventListener('input', filter);
    zoomIn.addEventListener('click', function () {
      zoomBy(0.8);
    });
    zoomOut.addEventListener('click', function () {
      zoomBy(1.25);
    });
    mount.addEventListener('wheel', function (event) {
      event.preventDefault();
      var rect = mount.getBoundingClientRect();
      var px = Math.min(Math.max((event.clientX - rect.left) / Math.max(rect.width, 1), 0), 1);
      var py = Math.min(Math.max((event.clientY - rect.top) / Math.max(rect.height, 1), 0), 1);
      zoomBy(event.deltaY < 0 ? 0.8 : 1.25, px, py);
    }, {passive: false});
    window.addEventListener('resize', fitBoxHeight);
    fitBoxHeight();
    fullscreenBtn.disabled = false;
    fullscreenBtn.addEventListener('click', function () {
      if (document.fullscreenElement) {
        document.exitFullscreen();
      } else {
        mount.requestFullscreen();
      }
    });
    document.addEventListener('fullscreenchange', function () {
      var on = document.fullscreenElement === mount;
      fullscreenBtn.classList.toggle('is-active', on);
      fullscreenBtn.setAttribute('aria-label', on ? 'Exit fullscreen' : 'Fullscreen');
      fullscreenBtn.title = on ? 'Exit fullscreen' : 'Fullscreen';
      if (!on) {
        fitBoxHeight();
      }
    });
    search.form.addEventListener('submit', function (event) {
      event.preventDefault();
    });
    var drag = null;
    var ignoreClick = false;
    mount.addEventListener('pointerdown', function (event) {
      if (!mount.classList.contains('is-focused') || !event.isPrimary || event.button !== 0) {
        return;
      }
      var viewBox = svg.viewBox.baseVal;
      drag = {
        pointerId: event.pointerId,
        clientX: event.clientX,
        clientY: event.clientY,
        moved: false,
        x: viewBox.x,
        y: viewBox.y,
        width: viewBox.width,
        height: viewBox.height
      };
      mount.setPointerCapture(event.pointerId);
      mount.classList.add('is-dragging');
      event.preventDefault();
    });
    mount.addEventListener('pointermove', function (event) {
      if (!drag || event.pointerId !== drag.pointerId) {
        return;
      }
      var deltaX = event.clientX - drag.clientX;
      var deltaY = event.clientY - drag.clientY;
      drag.moved = drag.moved || Math.abs(deltaX) > 3 || Math.abs(deltaY) > 3;
      var x = drag.x - deltaX * drag.width / Math.max(svg.clientWidth, 1);
      var y = drag.y - deltaY * drag.height / Math.max(svg.clientHeight, 1);
      if (originalBounds) {
        if (drag.width < originalBounds[2]) {
          x = Math.min(Math.max(x, originalBounds[0]), originalBounds[0] + originalBounds[2] - drag.width);
        }
        if (drag.height < originalBounds[3]) {
          y = Math.min(Math.max(y, originalBounds[1]), originalBounds[1] + originalBounds[3] - drag.height);
        }
      }
      svg.setAttribute('viewBox', [x, y, drag.width, drag.height].join(' '));
      event.preventDefault();
    });
    function finishDrag(event) {
      if (!drag || event.pointerId !== drag.pointerId) {
        return;
      }
      ignoreClick = event.type === 'pointerup' && drag.moved;
      if (drag.moved) {
        recordView();
      }
      drag = null;
      mount.classList.remove('is-dragging');
      if (mount.hasPointerCapture(event.pointerId)) {
        mount.releasePointerCapture(event.pointerId);
      }
    }
    mount.addEventListener('pointerup', finishDrag);
    mount.addEventListener('pointercancel', finishDrag);
    mount.addEventListener('click', function (event) {
      if (ignoreClick) {
        ignoreClick = false;
        return;
      }
      var node = event.target && event.target.closest ? event.target.closest('g.node') : null;
      if (node) {
        focusNodes([node]);
      }
    });
    mount.addEventListener('dblclick', function (event) {
      var node = event.target && event.target.closest ? event.target.closest('g.node') : null;
      if (!node) {
        return;
      }
      var fqcn = nodeClassName(node);
      var resolved = fqcn ? resolve(fqcn) : null;
      if (resolved) {
        window.open(resolved.gh, '_blank', 'noopener');
      }
    });
    mount.addEventListener('keydown', function (event) {
      if (!mount.classList.contains('is-focused')) {
        return;
      }
      if (event.key === 'Enter' || event.key === ' ' || event.key === 'Escape') {
        event.preventDefault();
        resetView();
      }
    });
    // Restore a view shared or left in the URL hash (#graph=x,y,width,height).
    var hashMatch = location.hash.match(/^#graph=(-?[\d.]+),(-?[\d.]+),(-?[\d.]+),(-?[\d.]+)$/);
    if (hashMatch) {
      var restored = [Number(hashMatch[1]), Number(hashMatch[2]), Number(hashMatch[3]), Number(hashMatch[4])];
      if (restored[2] > 0 && restored[3] > 0) {
        svg.setAttribute('viewBox', restored.join(' '));
        if (originalBounds && (restored[2] < originalBounds[2] || restored[3] < originalBounds[3])) {
          mount.classList.add('is-focused');
        }
      }
    }
    filter();
  }

  async function getViz() {
    for (var attempt = 0; attempt < 50; attempt += 1) {
      if (window.Viz && typeof window.Viz.instance === 'function') {
        return window.Viz;
      }
      await new Promise(function (resolve) {
        window.setTimeout(resolve, 100);
      });
    }

    return null;
  }

  async function render() {
    var dot;
    try {
      dot = JSON.parse(raw.textContent || '""');
    } catch (error) {
      showStatus('Invalid object-graph DOT payload. Click to open object-graph.dot.');
      return;
    }
    if (!dot) {
      showStatus('Empty object graph. Click to open object-graph.dot.');
      return;
    }
    var Viz = await getViz();
    if (!Viz) {
      showStatus('Could not load viz.js. Click to open object-graph.dot.');
      return;
    }

    try {
      var viz = await Viz.instance();
      var svg = viz.renderSVGElement(dot);
      sanitize(svg);
      svg.removeAttribute('width');
      svg.removeAttribute('height');
      mount.replaceChildren(svg);
      enableSearch(svg);
    } catch (error) {
      showStatus('Could not render object graph. Click to open object-graph.dot.');
    }
  }

  render();
}());
