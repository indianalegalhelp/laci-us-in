/**
 * @file
 * Enhances the authority type select dropdown with visual group separators.
 *
 * This file is part of LACI.
 * Copyright 2025-2026, Indiana Legal Help
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(function (Drupal) {
  'use strict';

  Drupal.behaviors.laciAuthorityTypeSelect = {
    attach: function (context) {
      const selects = context.querySelectorAll
        ? context.querySelectorAll('select[name="field_type"]')
        : [];

      selects.forEach(function (select) {
        if (select.dataset.laciProcessed) {
          return;
        }
        select.dataset.laciProcessed = '1';

        // Mark separator options as disabled and add a CSS class.
        const options = select.querySelectorAll('option');
        options.forEach(function (opt) {
          if (opt.value.indexOf('_sep_') === 0) {
            opt.disabled = true;
            opt.classList.add('laci-separator');
          }
          // Mark children (indented options).
          if (opt.textContent.match(/^\s{2,}/)) {
            opt.classList.add('laci-child-option');
          }
          // Mark group headers.
          if (opt.value.match(/^\d+$/) && opt.textContent.indexOf('---') === 0) {
            opt.classList.add('laci-group-header');
          }
        });
      });
    }
  };

})(Drupal);
