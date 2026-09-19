(function () {
  const page = document.body.dataset.page;

  /* ---------------- login ---------------- */
  if (page === "login") {
  }

  /* ---------------- register ---------------- */
  if (page === "register") {
    document.addEventListener("DOMContentLoaded", () => {
      const form = document.getElementById("register-form");
      const roleInput = form.querySelector('input[name="role"]');
      const garageFields = document.getElementById("garage-fields");
      const doneSection = document.getElementById("done");

      document.querySelectorAll(".choice").forEach((choice) => {
        choice.addEventListener("click", () => {
          document
            .querySelectorAll(".choice")
            .forEach((c) => c.classList.remove("choice--active"));
          choice.classList.add("choice--active");
          roleInput.value = choice.dataset.role;
          garageFields.classList.toggle(
            "hidden",
            choice.dataset.role !== "garage",
          );
        });
      });

      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        clearFieldErrors(form);
        if (!validateForm(form)) return;

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = "Creating account…";

        const payload = {
          role: roleInput.value,
          full_name: form.full_name.value.trim(),
          email: form.email.value.trim(),
          phone: form.phone.value.trim(),
          password: form.password.value,
          password2: form.password2.value,
          garage_address: form.garage_address
            ? form.garage_address.value.trim()
            : null,
          trading_licence: form.trading_licence
            ? form.trading_licence.value.trim()
            : null,
        };

        try {
          const res = await fetch("../../config/auth/register.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
          });
          const data = await res.json();

          if (!res.ok) {
            showServerErrors(form, data);
            return;
          }
          form.closest(".panel").classList.add("hidden");
          doneSection.classList.remove("hidden");
        } catch {
          alert("Network error — please try again.");
        } finally {
          submitBtn.disabled = false;
          submitBtn.textContent = "Create account";
        }
      });
    });

    function validateForm(form) {
      let valid = true;

      if (!form.role.value) valid = false;

      form.querySelectorAll("[data-required]").forEach((field) => {
        if (!field.value.trim()) {
          setFieldError(field, true);
          valid = false;
        }
      });

      if (
        form.email.value &&
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.value)
      ) {
        setFieldError(form.email, true);
        valid = false;
      }
      if (form.password.value.length < 8) {
        setFieldError(form.password, true);
        valid = false;
      }
      if (form.password.value !== form.password2.value) {
        setFieldError(form.password2, true);
        valid = false;
      }
      if (!form.terms.checked) valid = false;

      return valid;
    }

    function setFieldError(field, hasError) {
      field.closest(".field")?.classList.toggle("field--invalid", hasError);
    }
    function clearFieldErrors(form) {
      form
        .querySelectorAll(".field")
        .forEach((f) => f.classList.remove("field--invalid"));
    }
    function showServerErrors(form, data) {
      if (data.errors) {
        Object.entries(data.errors).forEach(([name, msg]) => {
          const field = form.querySelector(`[name="${name}"]`);
          if (field) setFieldError(field, true);
        });
      }
      alert(data.message || "Could not create account.");
    }
  }

  /* ---------------- forgot / reset ---------------- */
  if (page === "forgot-password") {
  }

  if (page === "reset-password") {
  }
})();
