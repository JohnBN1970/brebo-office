(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboPurchaseInvoiceViewer = {
    attach(context) {
      once('brebo-purchase-invoice-viewer', '[data-brebo-invoice-coding]', context).forEach((coding) => {
        const invoiceId = coding.getAttribute('data-invoice-id');
        const page = coding.closest('.brebo-finance-purchase-invoice');
        if (!invoiceId || !page) {
          return;
        }

        const tables = Array.from(page.querySelectorAll(':scope > table'));
        const details = tables.length > 0 ? tables[0] : null;
        const projectLink = coding.previousElementSibling && coding.previousElementSibling.tagName !== 'TABLE'
          ? coding.previousElementSibling
          : null;

        const workbench = document.createElement('section');
        workbench.className = 'brebo-finance-invoice-visual-workbench';
        workbench.setAttribute('aria-label', Drupal.t('Visuele factuurwerkbank'));

        const evidence = document.createElement('section');
        evidence.className = 'brebo-finance-invoice-evidence';
        const evidenceHeader = document.createElement('header');
        evidenceHeader.className = 'brebo-finance-invoice-panel-header';
        evidenceHeader.innerHTML = '<div><h2>' + Drupal.t('Originele factuur') + '</h2><p>' + Drupal.t('Canoniek bronbestand uit de centrale intake.') + '</p></div>';

        const open = document.createElement('a');
        open.className = 'button button--small';
        open.href = '/brebo-office/finance/purchase-invoices/' + encodeURIComponent(invoiceId) + '/original';
        open.target = '_blank';
        open.rel = 'noopener';
        open.textContent = Drupal.t('Origineel openen');
        evidenceHeader.appendChild(open);

        const frame = document.createElement('iframe');
        frame.className = 'brebo-finance-invoice-document';
        frame.src = open.href;
        frame.title = Drupal.t('Originele inkoopfactuur');
        frame.loading = 'eager';

        evidence.appendChild(evidenceHeader);
        evidence.appendChild(frame);

        const office = document.createElement('section');
        office.className = 'brebo-finance-invoice-office-panel';
        const officeHeader = document.createElement('header');
        officeHeader.className = 'brebo-finance-invoice-panel-header';
        officeHeader.innerHTML = '<div><h2>' + Drupal.t('BREBO Office') + '</h2><p>' + Drupal.t('Factuurgegevens, regels, codering en controle.') + '</p></div>';
        const officeBody = document.createElement('div');
        officeBody.className = 'brebo-finance-invoice-panel-body';

        if (details) {
          officeBody.appendChild(details);
        }
        if (projectLink && projectLink !== details) {
          officeBody.appendChild(projectLink);
        }
        officeBody.appendChild(coding);
        office.appendChild(officeHeader);
        office.appendChild(officeBody);

        workbench.appendChild(evidence);
        workbench.appendChild(office);

        const back = page.querySelector(':scope > p');
        if (back && back.nextSibling) {
          page.insertBefore(workbench, back.nextSibling);
        }
        else {
          page.insertBefore(workbench, page.firstChild);
        }
      });
    }
  };
})(Drupal, once);
