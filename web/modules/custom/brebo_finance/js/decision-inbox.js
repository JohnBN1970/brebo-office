(function (Drupal, once) {
  'use strict';

  const money = (value) => new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(Number(value || 0));
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const gateLabel = (gate) => ({procurement_release:'Inkoopvrijgave',execution_start:'Start uitvoering',billing_release:'Facturatievrijgave',payment_release:'Betaalvrijgave',project_closeout:'Projectafsluiting'}[gate] || gate);
  const levelLabel = (level) => ({gate_approver:'Goedkeurder',finance_controller:'Finance controller',executive:'Directie',executive_unresolved_exposure:'Directie · exposure onvolledig'}[level] || level);

  Drupal.behaviors.breboFinanceDecisionInbox = {
    attach(context) {
      once('brebo-finance-decisions', '#brebo-finance-decision-app', context).forEach((app) => {
        const list = app.querySelector('[data-bfd-list]');
        const status = app.querySelector('[data-bfd-status]');
        const panel = app.querySelector('[data-bfd-notifications]');
        const notificationList = app.querySelector('[data-bfd-notification-list]');
        const badge = app.querySelector('[data-bfd-badge]');
        const csrf = () => fetch('/session/token').then((r) => r.text());

        const loadNotifications = async () => {
          const data = await fetch(app.dataset.notificationsUrl, {credentials:'same-origin'}).then((r) => r.json());
          badge.textContent = data.unread_count || 0;
          badge.hidden = !data.unread_count;
          notificationList.innerHTML = data.items.length ? data.items.map((n) => `<button class="bfd-note" data-note-id="${n.id}" data-url="${esc(n.decision_url)}"><strong>${esc(gateLabel(n.payload.gate))}</strong><span>${esc(n.attention.replaceAll('_',' '))}</span></button>`).join('') : '<p class="bfd-empty">Geen nieuwe meldingen.</p>';
          notificationList.querySelectorAll('[data-note-id]').forEach((button) => button.addEventListener('click', async () => {
            const token = await csrf();
            await fetch(`${app.dataset.notificationsUrl}/${button.dataset.noteId}/read`, {method:'POST', credentials:'same-origin', headers:{'X-CSRF-Token':token}});
            const id = new URL(button.dataset.url, window.location.origin).searchParams.get('exception_id');
            document.querySelector(`[data-exception-id="${id}"]`)?.scrollIntoView({behavior:'smooth', block:'center'});
            panel.hidden = true;
            loadNotifications();
          }));
        };

        const decide = async (id, decision) => {
          const note = window.prompt(decision === 'approved' ? 'Korte motivatie voor goedkeuring:' : 'Reden van afwijzing:');
          if (note === null) return;
          const token = await csrf();
          const response = await fetch(`${app.dataset.decisionBaseUrl}/${id}/decision`, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','X-CSRF-Token':token}, body: JSON.stringify({decision, note})});
          if (!response.ok) throw new Error((await response.json().catch(() => ({}))).message || 'Besluit kon niet worden verwerkt.');
          await loadInbox(); await loadNotifications();
        };

        const loadInbox = async () => {
          status.textContent = 'Besluiten laden…';
          try {
            const data = await fetch(app.dataset.inboxUrl, {credentials:'same-origin'}).then((r) => r.json());
            const counts = {now:0,today:0,this_week:0}; let totalExposure = 0;
            data.items.forEach((item) => { counts[item.priority?.band || 'this_week']++; totalExposure += Number(item.exposure?.exposure_amount || 0); });
            status.innerHTML = data.count ? `<strong>${counts.now} NU</strong> · ${counts.today} VANDAAG · ${counts.this_week} DEZE WEEK · <strong>${money(totalExposure)} exposure</strong>` : 'Geen financiële besluiten die op jou wachten.';
            list.innerHTML = data.items.length ? data.items.map((item) => {
              const exposure = item.exposure?.exposure_amount ?? '0.00';
              const unresolved = item.exposure?.unresolved?.length || 0;
              const priority = item.priority || {band:'this_week', label:'Deze week', explanation:'Reguliere financiële beslissing.'};
              return `<article class="bfd-card bfd-priority-${esc(priority.band)}" data-exception-id="${item.exception_id}">
                <div class="bfd-card-top"><div><span class="bfd-priority">${esc(priority.label)}</span><span class="bfd-chip">${esc(gateLabel(item.gate))}</span><h2>Project #${item.project_nid}</h2></div><div class="bfd-money">${money(exposure)}</div></div>
                <p class="bfd-why"><strong>Waarom nu:</strong> ${esc(priority.explanation)}</p>
                <div class="bfd-meta"><span>${esc(levelLabel(item.authorization?.level))}</span><span>Verloopt: ${new Date(item.expires_at * 1000).toLocaleString('nl-NL')}</span>${unresolved ? `<span class="bfd-danger">${unresolved} exposure-item(s) onduidelijk</span>` : ''}</div>
                <div class="bfd-grid"><section><small>Reden</small><p>${esc(item.reason || 'Niet opgegeven')}</p></section><section><small>Beheersmaatregel</small><p>${esc(item.control_measure || 'Niet opgegeven')}</p></section></div>
                <div class="bfd-actions"><button class="bfd-reject" data-decision="rejected">Afwijzen</button><button class="bfd-approve" data-decision="approved">Goedkeuren</button></div>
              </article>`;
            }).join('') : '<div class="bfd-empty-state"><strong>Alles bijgewerkt.</strong><span>Er staan geen financiële besluiten voor jou open.</span></div>';
            list.querySelectorAll('[data-decision]').forEach((button) => button.addEventListener('click', async () => { button.disabled = true; try { await decide(button.closest('[data-exception-id]').dataset.exceptionId, button.dataset.decision); } catch (error) { window.alert(error.message); button.disabled = false; } }));
          } catch (error) { status.textContent = 'Beslisinbox kon niet worden geladen.'; list.innerHTML = `<div class="bfd-error">${esc(error.message)}</div>`; }
        };

        app.querySelector('[data-bfd-bell]').addEventListener('click', () => { panel.hidden = !panel.hidden; if (!panel.hidden) loadNotifications(); });
        app.querySelector('[data-bfd-close]').addEventListener('click', () => { panel.hidden = true; });
        loadInbox(); loadNotifications();
      });
    }
  };
})(Drupal, once);
