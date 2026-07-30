// just handles the booking form (dates/price) and the confirm popups
document.addEventListener("DOMContentLoaded", () => {
  const bookingForm = document.getElementById("bookingForm");
  if (bookingForm) {
    const dateInput  = bookingForm.querySelector('[name="booking_date"]');
    const startInput = bookingForm.querySelector('[name="start_time"]');
    const endInput   = bookingForm.querySelector('[name="end_time"]');
    const priceBox   = document.getElementById("livePrice");
    const rate       = parseFloat(bookingForm.dataset.rate || "0");

    // toISOString() converts to UTC first, so in Riyadh (UTC+3) anyone opening
    // this page between midnight and 3am got yesterday as the earliest date.
    // Build the string from the local calendar instead.
    const todayLocal = () => {
      const d = new Date();
      return [
        d.getFullYear(),
        String(d.getMonth() + 1).padStart(2, "0"),
        String(d.getDate()).padStart(2, "0"),
      ].join("-");
    };
    if (dateInput && !dateInput.min) dateInput.min = todayLocal();

    const minutes = (value) => {
      const [h, m] = value.split(":").map(Number);
      return h * 60 + m;
    };

    function updatePrice() {
      if (!priceBox) return;
      if (!startInput.value || !endInput.value) {
        priceBox.textContent = "--";
        return;
      }
      const hours = (minutes(endInput.value) - minutes(startInput.value)) / 60;
      priceBox.textContent = hours > 0 ? (hours * rate).toFixed(2) + " SAR" : "--";
    }

    // "input" as well as "change", so the total keeps up while someone is still
    // typing or holding down an arrow key.
    [startInput, endInput].forEach((el) => {
      el.addEventListener("change", updatePrice);
      el.addEventListener("input", updatePrice);
    });
    // Run once at load. The edit page arrives with both times already filled
    // in, and it used to show "--" until you touched one of them.
    updatePrice();

    bookingForm.addEventListener("submit", (e) => {
      if (!startInput.value || !endInput.value) return;
      // Compare the times as numbers. Comparing "9:00" < "18:00" as text works
      // by luck only while every value has a leading zero.
      if (minutes(endInput.value) <= minutes(startInput.value)) {
        e.preventDefault();
        alert("End time must be after start time.");
      }
    });
  }

  // Confirmation prompts. These sit on <button> elements inside small POST
  // forms now, so saying "no" has to stop the submit, not just the click.
  document.querySelectorAll("[data-confirm]").forEach((el) => {
    el.addEventListener("click", (e) => {
      if (!confirm(el.dataset.confirm)) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  });
});
