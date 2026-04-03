/**
 * Reorder and duplicate rule cards (pricing + fees).
 */
(function () {
  function renumberPricingCards(stack) {
    if (!stack) return;
    var cards = stack.querySelectorAll(".rwgcm-rule-card");
    cards.forEach(function (card, i) {
      card.querySelectorAll(".rwgcm-rule-card__order").forEach(function (el) {
        el.textContent = String(i + 1);
      });
      var hid = card.querySelector('input[type="hidden"][name^="rwgcm_rule_active["]');
      var cb = card.querySelector('input[type="checkbox"][name^="rwgcm_rule_active["]');
      var nameBase = "rwgcm_rule_active[" + i + "]";
      if (hid) hid.setAttribute("name", nameBase);
      if (cb) cb.setAttribute("name", nameBase);
      var catSel = card.querySelector('select[name^="rwgcm_rule_category_ids["]');
      if (catSel) {
        catSel.setAttribute("name", "rwgcm_rule_category_ids[" + i + "][]");
      }
    });
  }

  function renumberFeeCards(stack) {
    if (!stack) return;
    var cards = stack.querySelectorAll(".rwgcm-fee-card");
    cards.forEach(function (card, i) {
      card.querySelectorAll(".rwgcm-fee-card__order").forEach(function (el) {
        el.textContent = String(i + 1);
      });
      var hid = card.querySelector('input[type="hidden"][name^="rwgcm_fee_active["]');
      var cb = card.querySelector('input[type="checkbox"][name^="rwgcm_fee_active["]');
      var nameBase = "rwgcm_fee_active[" + i + "]";
      if (hid) hid.setAttribute("name", nameBase);
      if (cb) cb.setAttribute("name", nameBase);
      var taxCb = card.querySelector('input[type="checkbox"][name^="rwgcm_fee_taxable["]');
      if (taxCb) taxCb.setAttribute("name", "rwgcm_fee_taxable[" + i + "]");
    });
  }

  function moveCard(card, dir) {
    var stack = card.parentNode;
    if (dir < 0 && card.previousElementSibling) {
      stack.insertBefore(card, card.previousElementSibling);
    } else if (dir > 0 && card.nextElementSibling) {
      stack.insertBefore(card.nextElementSibling, card);
    }
  }

  function bindPricingStack(stack) {
    if (!stack || stack.dataset.rwgcmBound) return;
    stack.dataset.rwgcmBound = "1";
    stack.addEventListener("click", function (e) {
      var t = e.target;
      if (!t || !t.closest) return;
      var card = t.closest(".rwgcm-rule-card");
      if (!card || !stack.contains(card)) return;
      if (t.classList.contains("rwgcm-move-up")) {
        e.preventDefault();
        moveCard(card, -1);
        renumberPricingCards(stack);
      } else if (t.classList.contains("rwgcm-move-down")) {
        e.preventDefault();
        moveCard(card, 1);
        renumberPricingCards(stack);
      } else if (t.classList.contains("rwgcm-duplicate-card")) {
        e.preventDefault();
        var clone = card.cloneNode(true);
        stack.appendChild(clone);
        renumberPricingCards(stack);
      } else if (t.classList.contains("rwgcm-remove-card")) {
        e.preventDefault();
        if (stack.querySelectorAll(".rwgcm-rule-card").length <= 1) return;
        card.remove();
        renumberPricingCards(stack);
      }
    });
    var addBtn = document.getElementById("rwgcm-pricing-add-card");
    if (addBtn) {
      addBtn.addEventListener("click", function () {
        var last = stack.querySelector(".rwgcm-rule-card:last-child");
        if (!last) return;
        var clone = last.cloneNode(true);
        clone.querySelectorAll("input, select").forEach(function (el) {
          if (el.type === "checkbox") {
            el.checked = true;
          } else if (el.tagName === "SELECT") {
            el.selectedIndex = 0;
          } else {
            el.value = "";
          }
        });
        stack.appendChild(clone);
        renumberPricingCards(stack);
      });
    }
    renumberPricingCards(stack);
  }

  function bindFeeStack(stack) {
    if (!stack || stack.dataset.rwgcmBound) return;
    stack.dataset.rwgcmBound = "1";
    stack.addEventListener("click", function (e) {
      var t = e.target;
      if (!t || !t.closest) return;
      var card = t.closest(".rwgcm-fee-card");
      if (!card || !stack.contains(card)) return;
      if (t.classList.contains("rwgcm-move-up")) {
        e.preventDefault();
        moveCard(card, -1);
        renumberFeeCards(stack);
      } else if (t.classList.contains("rwgcm-move-down")) {
        e.preventDefault();
        moveCard(card, 1);
        renumberFeeCards(stack);
      } else if (t.classList.contains("rwgcm-duplicate-card")) {
        e.preventDefault();
        var clone = card.cloneNode(true);
        stack.appendChild(clone);
        renumberFeeCards(stack);
      } else if (t.classList.contains("rwgcm-remove-card")) {
        e.preventDefault();
        if (stack.querySelectorAll(".rwgcm-fee-card").length <= 1) return;
        card.remove();
        renumberFeeCards(stack);
      }
    });
    var addBtn = document.getElementById("rwgcm-fee-add-card");
    if (addBtn) {
      addBtn.addEventListener("click", function () {
        var last = stack.querySelector(".rwgcm-fee-card:last-child");
        if (!last) return;
        var clone = last.cloneNode(true);
        clone.querySelectorAll("input, select").forEach(function (el) {
          if (el.type === "checkbox") {
            el.checked = false;
          } else if (el.tagName === "SELECT") {
            el.selectedIndex = 0;
          } else {
            el.value = "";
          }
        });
        stack.appendChild(clone);
        renumberFeeCards(stack);
      });
    }
    renumberFeeCards(stack);
  }

  document.addEventListener("DOMContentLoaded", function () {
    bindPricingStack(document.getElementById("rwgcm-pricing-rule-stack"));
    bindFeeStack(document.getElementById("rwgcm-fee-rule-stack"));
  });
})();
