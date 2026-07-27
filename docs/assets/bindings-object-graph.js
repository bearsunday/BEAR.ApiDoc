(function () {
  'use strict';

  var mount = document.getElementById('object-graph-mount');
  var raw = document.getElementById('object-graph-dot');
  var search = document.getElementById('object-graph-search');
  var count = document.getElementById('object-graph-search-count');
  if (!mount || !raw || !search || !count) {
    return;
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

    function resetView() {
      if (originalViewBox) {
        svg.setAttribute('viewBox', originalViewBox);
      }
      mount.classList.remove('is-focused');
      mount.title = '';
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
      mount.title = 'Click to reset the object graph view';
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

    search.disabled = false;
    search.addEventListener('input', filter);
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
      drag = null;
      mount.classList.remove('is-dragging');
      if (mount.hasPointerCapture(event.pointerId)) {
        mount.releasePointerCapture(event.pointerId);
      }
    }
    mount.addEventListener('pointerup', finishDrag);
    mount.addEventListener('pointercancel', finishDrag);
    mount.addEventListener('click', function () {
      if (ignoreClick) {
        ignoreClick = false;
        return;
      }
      if (mount.classList.contains('is-focused')) {
        resetView();
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
