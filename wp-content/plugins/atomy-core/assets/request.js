(function ($) {
  'use strict';

  var cfg = window.atomyRequest || { ajaxUrl: '', nonce: '', shopUrl: '/' };
  var REMOVE_MS = 1000;
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  function updateHeaderCount(count) {
    $('.header-cart__count').text(count);
  }

  function sendQty(data, done) {
    return $.post(cfg.ajaxUrl, $.extend({ action: 'atomy_set_qty', nonce: cfg.nonce }, data))
      .done(function (res) {
        if (res && res.success && done) {
          done(res.data);
        }
      });
  }

  /* ================= Catalog: quantity stepper on product cards ================= */

  function loopWrapsFor(productId) {
    return $('[data-loop-buy][data-product-id="' + productId + '"]');
  }

  function showLoopStepper($wraps, qty) {
    $wraps.addClass('has-qty').removeClass('is-removing');
    $wraps.find('.atomy-qty__num').text(qty);
    $wraps.find('.atomy-qty--loop').prop('hidden', false);
  }

  function hideLoopStepper($wraps) {
    $wraps.removeClass('has-qty is-removing');
    $wraps.find('.atomy-qty--loop').prop('hidden', true);
    $wraps.find('.atomy-qty__num').text(1);
  }

  $(document.body).on('added_to_cart', function (e, fragments, cartHash, $button) {
    if (!$button || !$button.data('product_id')) {
      return;
    }
    showLoopStepper(loopWrapsFor($button.data('product_id')), 1);
  });

  var loopTimers = {};

  $(document).on('click', '[data-loop-buy] [data-qty-plus], [data-loop-buy] [data-qty-minus]', function () {
    var $btn = $(this);
    var $wrap = $btn.closest('[data-loop-buy]');
    var pid = String($wrap.attr('data-product-id'));
    var $wraps = loopWrapsFor(pid);
    var $num = $wrap.find('.atomy-qty__num').first();
    var next = (parseInt($num.text(), 10) || 0) + ($btn.is('[data-qty-plus]') ? 1 : -1);

    clearTimeout(loopTimers[pid]);

    if (next <= 0) {
      $wraps.addClass('is-removing');
      var started = Date.now();
      sendQty({ product_id: pid, qty: 0 }, function (data) {
        updateHeaderCount(data.cart_count);
        setTimeout(function () {
          hideLoopStepper($wraps);
        }, Math.max(0, REMOVE_MS - (Date.now() - started)));
      });
      return;
    }

    $wraps.find('.atomy-qty__num').text(next);
    loopTimers[pid] = setTimeout(function () {
      sendQty({ product_id: pid, qty: next }, function (data) {
        updateHeaderCount(data.cart_count);
      });
    }, 450);
  });

  /* ================= WooCommerce cart page: +/- around qty inputs ================= */

  function enhanceWcQty() {
    $('.woocommerce-cart-form td.product-quantity .quantity, form.cart .quantity').each(function () {
      var $q = $(this);
      if ($q.hasClass('atomy-qty-wc')) {
        return;
      }
      var $input = $q.find('input.qty');
      if (!$input.length) {
        return;
      }
      $q.addClass('atomy-qty-wc');
      $('<button type="button" class="atomy-qty__btn" data-wc-qty="-1" aria-label="Уменьшить количество">&minus;</button>').prependTo($q);
      $('<button type="button" class="atomy-qty__btn" data-wc-qty="1" aria-label="Увеличить количество">+</button>').appendTo($q);
    });
  }

  var wcCartTimer = null;

  $(document).on('click', '[data-wc-qty]', function () {
    var $btn = $(this);
    var $input = $btn.closest('.quantity').find('input.qty');
    var val = parseInt($input.val(), 10) || 0;
    var next = val + parseInt($btn.attr('data-wc-qty'), 10);
    var max = parseInt($input.attr('max'), 10);
    if (max && next > max) {
      next = max;
    }

    if (next <= 0) {
      clearTimeout(wcCartTimer);
      var $row = $btn.closest('tr');
      $row.addClass('atomy-row-removing');
      setTimeout(function () {
        $row.find('td.product-remove a').first().trigger('click');
      }, REMOVE_MS - 50);
      return;
    }

    $input.val(next).trigger('change');
    clearTimeout(wcCartTimer);
    wcCartTimer = setTimeout(function () {
      $('.woocommerce-cart-form :input[name="update_cart"]').prop('disabled', false).trigger('click');
    }, 600);
  });

  $(document.body).on('updated_wc_div removed_from_cart', function () {
    enhanceWcQty();
    $(document.body).trigger('wc_fragment_refresh');
  });

  $(enhanceWcQty);

  /* ================= Request page ================= */

  var $form = $('#atomy-request-form');
  if (!$form.length) {
    return;
  }

  var $birth = $('#atomy_birthdate');
  var now = new Date();
  var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  var MAX_YEAR = today.getFullYear();
  var MIN_YEAR = MAX_YEAR - 120;
  var minDate = new Date(MIN_YEAR, today.getMonth(), today.getDate());

  var MONTHS = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
  var WEEKDAYS = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

  function markField($input, ok) {
    $input.closest('.atomy-field').toggleClass('has-error', !ok);
    return ok;
  }

  /* ----- Request cart: quantity steppers ----- */

  var requestTimers = {};

  function replaceWithEmptyMessage() {
    $('.atomy-request-layout').replaceWith(
      '<p class="atomy-request-empty">Корзина пуста. <a href="' + cfg.shopUrl + '">Перейти в каталог</a></p>'
    );
  }

  $(document).on('click', '.atomy-request-cart-table [data-qty-plus], .atomy-request-cart-table [data-qty-minus]', function () {
    var $btn = $(this);
    var $stepper = $btn.closest('.atomy-qty');
    var key = String($stepper.attr('data-cart-key'));
    var $row = $btn.closest('tr');
    var $num = $stepper.find('.atomy-qty__num');
    var next = (parseInt($num.text(), 10) || 0) + ($btn.is('[data-qty-plus]') ? 1 : -1);

    clearTimeout(requestTimers[key]);

    if (next <= 0) {
      $row.addClass('atomy-row-removing');
      var started = Date.now();
      sendQty({ cart_key: key, qty: 0 }, function (data) {
        setTimeout(function () {
          $row.remove();
          $('[data-cart-total]').html(data.cart_total);
          updateHeaderCount(data.cart_count);
          if (data.cart_empty) {
            replaceWithEmptyMessage();
          }
        }, Math.max(0, REMOVE_MS - (Date.now() - started)));
      });
      return;
    }

    $num.text(next);
    requestTimers[key] = setTimeout(function () {
      sendQty({ cart_key: key, qty: next }, function (data) {
        if (data.line_total) {
          $row.find('[data-line-total]').text(data.line_total);
        }
        $('[data-cart-total]').html(data.cart_total);
        updateHeaderCount(data.cart_count);
      });
    }, 450);
  });

  /* ----- Birthdate input mask ----- */

  function clampDay(digits) {
    if (!digits.length) {
      return digits;
    }
    if (digits.length === 1 && parseInt(digits[0], 10) > 3) {
      return '3';
    }
    if (digits.length >= 2) {
      var day = parseInt(digits.slice(0, 2), 10);
      if (day < 1) {
        return '01' + digits.slice(2);
      }
      if (day > 31) {
        return '31' + digits.slice(2);
      }
    }
    return digits;
  }

  function clampMonth(digits) {
    if (digits.length <= 2) {
      return digits;
    }
    if (digits.length === 3 && parseInt(digits[2], 10) > 1) {
      return digits.slice(0, 2) + '1';
    }
    if (digits.length >= 4) {
      var month = parseInt(digits.slice(2, 4), 10);
      var head = digits.slice(0, 2);
      var tail = digits.slice(4);
      if (month < 1) {
        return head + '01' + tail;
      }
      if (month > 12) {
        return head + '12' + tail;
      }
    }
    return digits;
  }

  function clampYear(digits) {
    if (digits.length <= 4) {
      return digits;
    }
    var head = digits.slice(0, 4);
    var yearPart = digits.slice(4, 8);
    var minStr = String(MIN_YEAR);
    var maxStr = String(MAX_YEAR);

    for (var i = 1; i <= yearPart.length; i++) {
      var prefix = yearPart.slice(0, i);
      var minPrefix = minStr.slice(0, i);
      var maxPrefix = maxStr.slice(0, i);
      if (prefix > maxPrefix) {
        yearPart = maxPrefix + yearPart.slice(i);
      } else if (prefix < minPrefix && i === yearPart.length && yearPart.length === 4) {
        yearPart = minStr;
      }
    }

    if (yearPart.length === 4) {
      var year = parseInt(yearPart, 10);
      if (year > MAX_YEAR) {
        yearPart = maxStr;
      }
      if (year < MIN_YEAR) {
        yearPart = minStr;
      }
    }

    return head + yearPart;
  }

  function formatDigits(raw) {
    var digits = raw.replace(/\D/g, '').slice(0, 8);
    digits = clampDay(digits);
    digits = clampMonth(digits);
    digits = clampYear(digits);

    if (digits.length > 4) {
      return digits.slice(0, 2) + '.' + digits.slice(2, 4) + '.' + digits.slice(4);
    }
    if (digits.length > 2) {
      return digits.slice(0, 2) + '.' + digits.slice(2);
    }
    return digits;
  }

  function pad2(n) {
    return (n < 10 ? '0' : '') + n;
  }

  function formatDate(date) {
    return pad2(date.getDate()) + '.' + pad2(date.getMonth() + 1) + '.' + date.getFullYear();
  }

  function parseDate(value) {
    var m = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(value || '');
    if (!m) {
      return null;
    }
    var day = parseInt(m[1], 10);
    var month = parseInt(m[2], 10);
    var year = parseInt(m[3], 10);
    var date = new Date(year, month - 1, day);
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
      return null;
    }
    return date;
  }

  function isValidBirthdate(value) {
    var date = parseDate(value);
    return !!date && date >= minDate && date <= today;
  }

  $birth.on('input', function () {
    var formatted = formatDigits(this.value);
    if (this.value !== formatted) {
      this.value = formatted;
    }
    if (this.value.length === 10) {
      markField($birth, isValidBirthdate(this.value));
    } else {
      markField($birth, true);
    }
  });

  $birth.on('blur', function () {
    if (this.value.length > 0) {
      markField($birth, isValidBirthdate(this.value));
    }
  });

  /* ----- Datepicker ----- */

  var $control = $birth.closest('[data-birthdate-control]');
  var $toggle = $control.find('[data-cal-toggle]');
  var $panel = $('<div class="atomy-datepicker" hidden></div>').appendTo($control);
  var viewYear = MAX_YEAR - 30;
  var viewMonth = 0;

  function monthIndex(year, month) {
    return year * 12 + month;
  }

  function renderCalendar() {
    var selected = parseDate($birth.val());
    var m;
    var y;
    var html = '<div class="atomy-datepicker__head">';
    html += '<button type="button" class="atomy-datepicker__nav" data-cal-prev aria-label="Предыдущий месяц"'
      + (monthIndex(viewYear, viewMonth) <= monthIndex(MIN_YEAR, minDate.getMonth()) ? ' disabled' : '') + '>&#8249;</button>';
    html += '<select class="atomy-datepicker__select" data-cal-month aria-label="Месяц">';
    for (m = 0; m < 12; m++) {
      html += '<option value="' + m + '"' + (m === viewMonth ? ' selected' : '') + '>' + MONTHS[m] + '</option>';
    }
    html += '</select>';
    html += '<select class="atomy-datepicker__select atomy-datepicker__select--year" data-cal-year aria-label="Год">';
    for (y = MAX_YEAR; y >= MIN_YEAR; y--) {
      html += '<option value="' + y + '"' + (y === viewYear ? ' selected' : '') + '>' + y + '</option>';
    }
    html += '</select>';
    html += '<button type="button" class="atomy-datepicker__nav" data-cal-next aria-label="Следующий месяц"'
      + (monthIndex(viewYear, viewMonth) >= monthIndex(MAX_YEAR, today.getMonth()) ? ' disabled' : '') + '>&#8250;</button>';
    html += '</div>';

    html += '<div class="atomy-datepicker__week">';
    for (m = 0; m < WEEKDAYS.length; m++) {
      html += '<span>' + WEEKDAYS[m] + '</span>';
    }
    html += '</div>';

    html += '<div class="atomy-datepicker__grid">';
    var offset = (new Date(viewYear, viewMonth, 1).getDay() + 6) % 7;
    var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
    for (m = 0; m < offset; m++) {
      html += '<span></span>';
    }
    for (var d = 1; d <= daysInMonth; d++) {
      var date = new Date(viewYear, viewMonth, d);
      var cls = 'atomy-datepicker__day';
      if (selected && date.getTime() === selected.getTime()) {
        cls += ' is-selected';
      }
      if (date.getTime() === today.getTime()) {
        cls += ' is-today';
      }
      var disabled = date < minDate || date > today;
      html += '<button type="button" class="' + cls + '" data-cal-day="' + d + '"' + (disabled ? ' disabled' : '') + '>' + d + '</button>';
    }
    html += '</div>';
    $panel.html(html);
  }

  function calendarOpen() {
    return !$panel.prop('hidden');
  }

  function openCalendar() {
    var base = parseDate($birth.val()) || new Date(MAX_YEAR - 30, 0, 1);
    if (base < minDate) {
      base = minDate;
    }
    if (base > today) {
      base = today;
    }
    viewYear = base.getFullYear();
    viewMonth = base.getMonth();
    renderCalendar();
    $panel.prop('hidden', false);
    $toggle.attr('aria-expanded', 'true');
  }

  function closeCalendar() {
    $panel.prop('hidden', true);
    $toggle.attr('aria-expanded', 'false');
  }

  function stepMonth(delta) {
    var idx = monthIndex(viewYear, viewMonth) + delta;
    var min = monthIndex(MIN_YEAR, minDate.getMonth());
    var max = monthIndex(MAX_YEAR, today.getMonth());
    idx = Math.min(Math.max(idx, min), max);
    viewYear = Math.floor(idx / 12);
    viewMonth = idx % 12;
    renderCalendar();
  }

  $toggle.on('click', function () {
    if (calendarOpen()) {
      closeCalendar();
    } else {
      openCalendar();
    }
  });

  $panel.on('click', '[data-cal-prev]', function () {
    stepMonth(-1);
  });

  $panel.on('click', '[data-cal-next]', function () {
    stepMonth(1);
  });

  $panel.on('change', '[data-cal-month]', function () {
    viewMonth = parseInt(this.value, 10);
    renderCalendar();
  });

  $panel.on('change', '[data-cal-year]', function () {
    viewYear = parseInt(this.value, 10);
    renderCalendar();
  });

  $panel.on('click', '[data-cal-day]', function () {
    var date = new Date(viewYear, viewMonth, parseInt($(this).attr('data-cal-day'), 10));
    $birth.val(formatDate(date));
    markField($birth, true);
    closeCalendar();
    $toggle.trigger('focus');
  });

  $(document).on('mousedown', function (e) {
    if (calendarOpen() && !$(e.target).closest('[data-birthdate-control]').length) {
      closeCalendar();
    }
  });

  $(document).on('keydown', function (e) {
    if ('Escape' === e.key && calendarOpen()) {
      closeCalendar();
      $toggle.trigger('focus');
    }
  });

  /* ----- Validation + submit ----- */

  $form.on('input', '.atomy-field input:not(#atomy_birthdate)', function () {
    $(this).closest('.atomy-field').removeClass('has-error');
  });

  function validateForm() {
    var checks = [
      [$('#atomy_name'), $('#atomy_name').val().trim() !== ''],
      [$('#atomy_email'), EMAIL_RE.test($('#atomy_email').val().trim())],
      [$('#atomy_phone'), $('#atomy_phone').val().trim() !== ''],
      [$birth, isValidBirthdate($birth.val())],
      [$('#atomy_city'), $('#atomy_city').val().trim() !== '']
    ];
    var $firstInvalid = null;
    checks.forEach(function (check) {
      markField(check[0], check[1]);
      if (!check[1] && !$firstInvalid) {
        $firstInvalid = check[0];
      }
    });
    if ($firstInvalid) {
      $firstInvalid.trigger('focus');
      return false;
    }
    return true;
  }

  $form.on('submit', function (e) {
    e.preventDefault();
    var $msg = $form.find('.atomy-request-message');
    $msg.removeClass('is-success is-error').text('');

    if (!validateForm()) {
      return;
    }

    $msg.text('Отправка...');

    $.post(cfg.ajaxUrl, {
      action: 'atomy_submit_request',
      nonce: cfg.nonce,
      name: $('#atomy_name').val(),
      email: $('#atomy_email').val(),
      phone: $('#atomy_phone').val(),
      birthdate: $birth.val(),
      city: $('#atomy_city').val(),
      atomy_hp: $form.find('[name="atomy_hp"]').val()
    })
      .done(function (res) {
        if (res.success) {
          $msg.addClass('is-success').text(res.data.message || 'Готово.');
          $form[0].reset();
          updateHeaderCount(0);
        } else {
          $msg.addClass('is-error').text((res.data && res.data.message) || 'Ошибка.');
        }
      })
      .fail(function (xhr) {
        var message = 'Ошибка отправки.';
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          message = xhr.responseJSON.data.message;
        }
        $msg.addClass('is-error').text(message);
      });
  });
})(jQuery);
