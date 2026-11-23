<style type="text/tailwindcss">
  /* No celular: fundo suave pros cards "flutuarem" como app */
  @media (max-width:1023px){ body{ background:#eef2f7; overflow-x:hidden; } }
  .sec { @apply rounded-2xl flex flex-col overflow-hidden; background:#fff; box-shadow: 0 1px 2px rgba(15,23,42,.05), 0 14px 34px -20px rgba(15,23,42,.38); }
  .sec-h { @apply flex items-center gap-2.5 px-4 py-3 text-white; background: linear-gradient(100deg, var(--cor1), color-mix(in srgb, var(--cor1) 72%, black)); }
  .sec-h .ic { @apply text-[15px] leading-none -ml-0.5 mr-0.5; }
  .sec-h .n { @apply inline-flex w-6 h-6 rounded-full items-center justify-center text-[12px] font-extrabold text-slate-900 shadow-sm; background: var(--cor2); }
  .sec-h .t { @apply text-[13px] font-bold uppercase tracking-wide; }
  .sec-b { @apply p-3.5 flex-1 flex flex-col gap-3; }
  .field { @apply rounded-xl bg-slate-50 border border-slate-200 px-3.5 py-2.5 text-[15px] w-full transition; min-height: 46px; }
  .field:focus { @apply outline-none bg-white; border-color: var(--cor2); box-shadow: 0 0 0 3px color-mix(in srgb, var(--cor2) 25%, transparent); }
  .lbl { @apply block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1; }
  .atkbd { @apply inline-flex items-center justify-center rounded px-1 text-[10px] font-extrabold leading-none align-middle; background: var(--cor2); color:#0b1a33; min-width:18px; height:16px; }
  .forma { @apply flex-1 rounded-xl border-2 py-2.5 text-[13px] font-bold text-center cursor-pointer transition text-slate-500 border-slate-200 bg-white; }
  .forma.on { background: var(--cor1); color:#fff; border-color: var(--cor1); }
  .pax { @apply rounded-xl p-3 border; background:#fff; border-color:#e8edf3; box-shadow:0 1px 2px rgba(15,23,42,.04); }
  .pax:nth-child(even){ background:#fbfcfe; }
  .pessoa { @apply rounded-xl p-3 space-y-2 border; background: color-mix(in srgb, var(--cor2) 7%, white); border-color: color-mix(in srgb, var(--cor2) 30%, white); }
  @media (min-width:1024px){
    .field{min-height:36px;font-size:14px;padding-top:.3rem;padding-bottom:.3rem}
    .sec-b{padding:.55rem .6rem;gap:.45rem}
    .sec-h{padding-top:.3rem;padding-bottom:.3rem}
    .sec-h .ic{display:none}
    .lbl{margin-bottom:.1rem;font-size:10px}
    .forma{padding-top:.4rem;padding-bottom:.4rem}
  }
</style>
