(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboProjectPortfolio = {
    attach(context) {
      once('brebo-project-gantt', '[data-brebo-project-gantt]', context).forEach((root) => {
        const canvas = root.querySelector('[data-brebo-gantt-canvas]');
        if (!canvas) return;

        let projects = [];
        try {
          projects = JSON.parse(root.dataset.projects || '[]');
        }
        catch (e) {
          canvas.innerHTML = '<p>Planning kon niet worden geladen.</p>';
          return;
        }
        if (!projects.length) return;

        const parse = (value) => new Date(value + 'T00:00:00');
        const minDate = new Date(Math.min(...projects.map((p) => parse(p.start).getTime())));
        const maxDate = new Date(Math.max(...projects.map((p) => parse(p.end).getTime())));
        const totalMs = Math.max(86400000, maxDate.getTime() - minDate.getTime());
        const signalClass = (signal) => `brebo-project-gantt__bar--${signal}`;

        const render = (scale) => {
          const day = 86400000;
          const paddingDays = scale === 'month' ? 7 : (scale === 'year' ? 60 : 21);
          const from = new Date(minDate.getTime() - paddingDays * day);
          const to = new Date(maxDate.getTime() + paddingDays * day);
          const span = Math.max(day, to.getTime() - from.getTime());
          const today = new Date();
          today.setHours(0, 0, 0, 0);

          let html = '<div class="brebo-project-gantt__header"><div>Project</div><div class="brebo-project-gantt__axis">';
          const markerCount = scale === 'month' ? 8 : (scale === 'year' ? 12 : 10);
          for (let i = 0; i <= markerCount; i++) {
            const d = new Date(from.getTime() + (span * i / markerCount));
            const left = (i / markerCount) * 100;
            const label = scale === 'year'
              ? d.toLocaleDateString('nl-NL', { month: 'short', year: '2-digit' })
              : d.toLocaleDateString('nl-NL', { day: 'numeric', month: 'short' });
            html += `<span style="left:${left}%">${label}</span>`;
          }
          html += '</div></div>';

          const todayLeft = ((today.getTime() - from.getTime()) / span) * 100;
          projects.forEach((project) => {
            const start = parse(project.start).getTime();
            const end = parse(project.end).getTime();
            const left = Math.max(0, ((start - from.getTime()) / span) * 100);
            const width = Math.max(1.2, ((Math.max(end, start + day) - start) / span) * 100);
            html += `<div class="brebo-project-gantt__row">`;
            html += `<div class="brebo-project-gantt__project"><a href="${project.url}"><strong>${project.title}</strong></a><span>${project.code || ''}</span></div>`;
            html += '<div class="brebo-project-gantt__track">';
            if (todayLeft >= 0 && todayLeft <= 100) html += `<span class="brebo-project-gantt__today" style="left:${todayLeft}%" title="Vandaag"></span>`;
            html += `<a class="brebo-project-gantt__bar ${signalClass(project.signal)}" href="${project.url}" style="left:${left}%;width:${width}%" title="${project.title}: ${project.start} – ${project.end}"><span class="brebo-project-gantt__progress" style="width:${Math.max(0, Math.min(100, project.progress))}%"></span><b>${project.progress}%</b></a>`;
            html += '</div></div>';
          });
          canvas.innerHTML = html;
        };

        root.querySelectorAll('[data-scale]').forEach((button) => {
          button.addEventListener('click', () => {
            root.querySelectorAll('[data-scale]').forEach((other) => other.classList.remove('is-active'));
            button.classList.add('is-active');
            render(button.dataset.scale || 'quarter');
          });
        });
        render('quarter');
      });
    },
  };
})(Drupal, once);

(function (Drupal, once) {
  Drupal.behaviors.breboProjectRowActionDirection = {
    attach(context) {
      once('brebo-project-row-action-direction', '.brebo-project-row-actions__more', context).forEach((details) => {
        details.addEventListener('toggle', () => {
          if (!details.open) return;
          const rect = details.getBoundingClientRect();
          const menuHeight = details.querySelector('.details-wrapper')?.scrollHeight || 150;
          const table = details.closest('table');
          const clipRect = table?.getBoundingClientRect() || { top: 0, bottom: window.innerHeight };
          const spaceBelow = clipRect.bottom - rect.bottom;
          const spaceAbove = rect.top - clipRect.top;
          details.classList.toggle('is-up', spaceBelow < menuHeight && spaceAbove > spaceBelow);
        });
      });
    },
  };
})(Drupal, once);
