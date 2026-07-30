// just handles the booking form (dates/price) and the confirm popups
document.addEventListener("DOMContentLoaded", () => {
  const bookingForm = document.getElementById("bookingForm");
  if (bookingForm) {
    const dateInput  = bookingForm.querySelector('[name="booking_date"]');
    const startInput = bookingForm.querySelector('[name="start_time"]');
    const endInput   = bookingForm.querySelector('[name="end_time"]');
    const priceBox   = document.getElementById("livePrice");
    const rate       = parseFloat(bookingForm.dataset.rate || "0");

    if (dateInput) dateInput.min = new Date().toISOString().split("T")[0];

    function updatePrice() {
      if (!startInput.value || !endInput.value || !priceBox) return;
      const [sh, sm] = startInput.value.split(":").map(Number);
      const [eh, em] = endInput.value.split(":").map(Number);
      const hours = (eh + em / 60) - (sh + sm / 60);
      priceBox.textContent = hours > 0 ? (hours * rate).toFixed(2) + " SAR" : "--";
    }
    startInput.addEventListener("change", updatePrice);
    endInput.addEventListener("change", updatePrice);

    bookingForm.addEventListener("submit", (e) => {
      if (endInput.value <= startInput.value) {
        e.preventDefault();
        alert("End time must be after start time.");
      }
    });
  }

  document.querySelectorAll("[data-confirm]").forEach((el) => {
    el.addEventListener("click", (e) => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });
});
