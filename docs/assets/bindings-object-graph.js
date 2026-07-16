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
    var nodes = Array.from(svg.querySelectorAll('g.node'));
    var edges = Array.from(svg.querySelectorAll('g.edge'));
    var searchable = nodes.map(function (node) {
      var labels = Array.from(node.querySelectorAll('text')).map(function (label) {
        return label.textContent || '';
      });

      return {element: node, text: labels.join('\\').toLowerCase()};
    });

    function filter() {
      var query = search.value.trim().toLowerCase();
      var matches = 0;
      searchable.forEach(function (node) {
        var match = query === '' || node.text.includes(query);
        node.element.classList.toggle('is-match', query !== '' && match);
        node.element.classList.toggle('is-dimmed', !match);
        matches += match ? 1 : 0;
      });
      edges.forEach(function (edge) {
        edge.classList.toggle('is-dimmed', query !== '');
      });
      count.textContent = matches + ' / ' + nodes.length;
    }

    search.disabled = false;
    search.addEventListener('input', filter);
    search.form.addEventListener('submit', function (event) {
      event.preventDefault();
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
      var serialized = new XMLSerializer().serializeToString(svg);
      mount.href = URL.createObjectURL(new Blob([serialized], {type: 'image/svg+xml'}));
      mount.title = 'Open full-size object graph';
      mount.replaceChildren(svg);
      enableSearch(svg);
    } catch (error) {
      showStatus('Could not render object graph. Click to open object-graph.dot.');
    }
  }

  render();
}());
