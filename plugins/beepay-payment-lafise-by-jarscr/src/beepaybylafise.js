function formatStringBeepayByLafise(event) {
  var inputChar = String.fromCharCode(event.keyCode);
  var code = event.keyCode;
  var allowedKeys = [8];
  if (allowedKeys.indexOf(code) !== -1) {
    return;
  }

  event.target.value = event.target.value
    .replace(
      /^([1-9]\/|[2-9])$/g,
      "0$1/", // 3 > 03/
    )
    .replace(
      /^(0[1-9]|1[0-2])$/g,
      "$1/", // 11 > 11/
    )
    .replace(
      /^([0-1])([3-9])$/g,
      "0$1/$2", // 13 > 01/3
    )
    .replace(
      /^(0?[1-9]|1[0-2])([0-9]{2})$/g,
      "$1/$2", // 141 > 01/41
    )
    .replace(
      /^([0]+)\/|[0]+$/g,
      "0", // 0/ > 0 and 00 > 0
    )
    .replace(
      /[^\d\/]|^[\/]*$/g,
      "", // To allow only digits and `/`
    )
    .replace(
      /\/\//g,
      "/", // Prevent entering more than 1 `/`
    );
}

function beepaybylafise_format(event) {
  var code = event.keyCode;
  var allowedKeys = [8];
  if (allowedKeys.indexOf(code) !== -1) {
    return;
  }

  var valor = document.getElementById("ccNoCRBE").value;

  var v = valor.replace(/\s+/g, "").replace(/[^0-9]/gi, "");
  var matches = v.match(/\d{4,16}/g);
  var match = (matches && matches[0]) || "";
  var parts = [];
  for (i = 0, len = match.length; i < len; i += 4) {
    parts.push(match.substring(i, i + 4));
  }
  if (parts.length) {
    document.getElementById("ccNoCRBE").value = parts.join(" ");
  } else {
    document.getElementById("ccNoCRBE").value = valor;
    BEEPAYCardTypeListener(valor);
  }
}

function BEEPAYCardTypeListener(value) {
  if (/^(34|37)/.test(value)) {
    changeBEEPAYCardTypeName("America Express");
  } else if (/^4/.test(value)) {
    changeBEEPAYCardTypeName("Visa");
  } else if (/^5[0-5]/.test(value)) {
    changeBEEPAYCardTypeName("MasterCard");
  } else if (/^(6011|622|64[4-9]|65)/.test(value)) {
    changeBEEPAYCardTypeName("Incorrecta");
  } else {
    changeBEEPAYCardTypeName("other");
  }
}

function changeBEEPAYCardTypeName(cardType) {
  document.getElementById("cardtypeBE").value = cardType;
}

jQuery(document).ready(function ($) {
  var modal = document.getElementById("paymentModal");
  var btn = document.getElementById("openPaymentModal");
  var span = document.getElementsByClassName("close")[0];
  var iframe = document.getElementById("paymentFrame");
  var form = document.getElementById("beepay_payment_form");

  if (!modal || !btn || !form) {
    return;
  }

  btn.onclick = function () {
    modal.style.display = "block";
    form.submit();
  };

  if (span) {
    span.onclick = function () {
      modal.style.display = "none";
      iframe.src = "about:blank";
    };
  }

  window.onclick = function (event) {
    if (event.target == modal) {
      modal.style.display = "none";
      iframe.src = "about:blank";
    }
  };

  if (iframe) {
    iframe.onload = function () {
      try {
        console.log("Iframe cargado");
      } catch (e) {
        console.log("Error al cargar iframe:", e);
      }
    };
  }
});
