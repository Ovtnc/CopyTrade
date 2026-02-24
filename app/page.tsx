"use client";

import { useMemo, useState } from "react";
import { motion } from "framer-motion";
import {
  Activity,
  ArrowUpRight,
  Bot,
  Cable,
  CircleCheck,
  LayoutDashboard,
  Menu,
  PackageSearch,
  PlugZap,
  WalletCards,
  XCircle,
} from "lucide-react";

import { ProgressRing } from "../components/dashboard/progress-ring";
import { StatCard } from "../components/dashboard/stat-card";

const sidebarMenu = [
  { label: "Overview", icon: LayoutDashboard, active: true },
  { label: "XML Sources", icon: Cable, active: false },
  { label: "Smart Mapping", icon: PlugZap, active: false },
  { label: "Products", icon: PackageSearch, active: false },
  { label: "Logs", icon: Activity, active: false },
  { label: "Billing", icon: WalletCards, active: false },
];

const pipelineLogs = [
  { status: "success", text: "TR-98231 urunu Trendyol'a gonderildi.", time: "12 sn once" },
  { status: "success", text: "HB-55421 icin stok guncellendi.", time: "45 sn once" },
  { status: "error", text: "TR-42210 fiyat dogrulamasindan gecemedi.", time: "2 dk once" },
  { status: "success", text: "XML feed parse edildi: 1.284 kayit.", time: "5 dk once" },
];

export default function OverviewPage() {
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);

  const stats = useMemo(
    () => [
      {
        title: "Aktif XML Kaynagi",
        value: "12",
        delta: "+2 bu hafta",
        icon: Cable,
        tone: "indigo" as const,
      },
      {
        title: "Bugun Senkronlanan Urun",
        value: "8,421",
        delta: "%97 basari orani",
        icon: CircleCheck,
        tone: "emerald" as const,
      },
      {
        title: "Hata/Retry Kuyrugu",
        value: "46",
        delta: "-18 son 24 saat",
        icon: XCircle,
        tone: "rose" as const,
      },
      {
        title: "AI Magic Fix Kullanimi",
        value: "2,104",
        delta: "%38 daha iyi CTR",
        icon: Bot,
        tone: "violet" as const,
      },
    ],
    [],
  );

  return (
    <div className="min-h-screen bg-[#06070b] text-white">
      <div className="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(99,102,241,.15),transparent_35%),radial-gradient(circle_at_80%_0%,rgba(139,92,246,.15),transparent_40%)]" />

      <div className="relative flex min-h-screen">
        <aside
          className={`hidden border-r border-white/10 bg-slate-950/60 backdrop-blur-xl transition-all md:block ${
            isSidebarCollapsed ? "w-[88px]" : "w-[260px]"
          }`}
        >
          <div className="flex items-center justify-between px-4 py-6">
            {!isSidebarCollapsed && (
              <div>
                <p className="text-xs uppercase tracking-[0.18em] text-violet-300">OmniConnect</p>
                <p className="mt-1 text-sm text-slate-400">Marketplace Intelligence</p>
              </div>
            )}
            <button
              onClick={() => setIsSidebarCollapsed((prev) => !prev)}
              className="rounded-lg border border-white/10 bg-white/5 p-2 text-slate-200 hover:bg-white/10"
              type="button"
            >
              <Menu className="h-4 w-4" />
            </button>
          </div>

          <nav className="space-y-1 px-3">
            {sidebarMenu.map((item) => (
              <button
                key={item.label}
                type="button"
                className={`flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition ${
                  item.active
                    ? "bg-violet-500/15 text-violet-200 ring-1 ring-violet-500/30"
                    : "text-slate-300 hover:bg-white/5"
                }`}
              >
                <item.icon className="h-4 w-4" />
                {!isSidebarCollapsed && <span>{item.label}</span>}
              </button>
            ))}
          </nav>
        </aside>

        <main className="flex-1 p-5 md:p-8">
          <header className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-xl md:p-6">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-violet-300">Dashboard Overview</p>
                <h1 className="mt-2 text-2xl font-semibold md:text-3xl">OmniConnect Control Center</h1>
                <p className="mt-1 text-sm text-slate-300">
                  XML kaynaklarini yonetin, AI ile urunleri optimize edin ve marketplace yuklemelerini gercek
                  zamanli takip edin.
                </p>
              </div>

              <button
                type="button"
                className="inline-flex items-center gap-2 rounded-xl border border-violet-400/50 bg-violet-500/20 px-4 py-2 text-sm font-medium text-violet-100 hover:bg-violet-500/30"
              >
                Yeni XML Kaynagi Ekle
                <ArrowUpRight className="h-4 w-4" />
              </button>
            </div>
          </header>

          <section className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {stats.map((stat, index) => (
              <motion.div key={stat.title} transition={{ delay: index * 0.04 }}>
                <StatCard {...stat} />
              </motion.div>
            ))}
          </section>

          <section className="mt-6 grid gap-4 xl:grid-cols-3">
            <div className="space-y-4 xl:col-span-2">
              <div className="grid gap-4 md:grid-cols-2">
                <ProgressRing label="Trendyol Sync Progress" value={82} hint="Dakikada 90 urun gonderiliyor." />
                <ProgressRing label="Hepsiburada Sync Progress" value={68} hint="Retry kuyrugu aktif: 14 urun." />
              </div>

              <div className="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl">
                <div className="mb-4 flex items-center justify-between">
                  <h2 className="text-sm font-medium text-slate-200">Gercek Zamanli Islem Loglari</h2>
                  <span className="rounded-full border border-emerald-400/40 bg-emerald-500/15 px-2.5 py-1 text-xs text-emerald-200">
                    Live
                  </span>
                </div>

                <div className="space-y-2">
                  {pipelineLogs.map((log) => (
                    <div
                      key={`${log.text}-${log.time}`}
                      className="flex items-start justify-between rounded-xl border border-white/10 bg-black/20 px-3 py-2.5"
                    >
                      <div className="flex items-start gap-2">
                        {log.status === "success" ? (
                          <CircleCheck className="mt-0.5 h-4 w-4 text-emerald-300" />
                        ) : (
                          <XCircle className="mt-0.5 h-4 w-4 text-rose-300" />
                        )}
                        <p className="text-sm text-slate-200">{log.text}</p>
                      </div>
                      <span className="text-xs text-slate-400">{log.time}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="space-y-4">
              <div className="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl">
                <h2 className="text-sm font-medium text-slate-200">AI Enhancement Engine</h2>
                <p className="mt-2 text-sm text-slate-300">
                  Magic Fix ile urun basliklarini optimize edin, karakter limitlerini otomatik dengeleyin.
                </p>
                <button
                  type="button"
                  className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-indigo-400/40 bg-indigo-500/20 px-3 py-2 text-sm font-medium text-indigo-100 hover:bg-indigo-500/30"
                >
                  <Bot className="h-4 w-4" />
                  Magic Fix'i Calistir
                </button>
              </div>

              <div className="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl">
                <h2 className="text-sm font-medium text-slate-200">Dinamik Fiyat Kurali</h2>
                <div className="mt-3 rounded-xl border border-white/10 bg-black/20 p-3 text-sm text-slate-300">
                  XML Fiyati + <span className="font-semibold text-white">%12</span> kar marji +{" "}
                  <span className="font-semibold text-white">9.90 TRY</span> sabit ekleme
                </div>
                <button
                  type="button"
                  className="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-sm text-slate-100 hover:bg-white/15"
                >
                  Kural Motorunu Duzenle
                </button>
              </div>
            </div>
          </section>
        </main>
      </div>
    </div>
  );
}
