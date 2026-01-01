/* Header color override - Blue brand name */
:root {
  /* اختر الدرجة هنا */
  --brand-blue: #0d6efd; /* bootstrap primary blue */
  --brand-blue-dark: #0b5ed7;
}

/* استهداف أكثر من محدد محتمل لاسم الموقع في الهيدر */
.site-header .brand,
.site-header .brand a,
.site-header .brand h1,
.header-brand h1,
.navbar-brand,
.site-title,
.header-title {
  color: var(--brand-blue) !important;
}

/* لو الاسم داخل رابط */
.site-header .brand a:hover,
.navbar-brand:hover {
  color: var(--brand-blue-dark) !important;
}

/* دعم الوضع الداكن - اضبط إن أردت */
html.dark .site-header .brand,
html.dark .site-header .brand h1 {
  color: #9ecbff !important;
}