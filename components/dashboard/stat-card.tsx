"use client";

import { motion } from "framer-motion";
import type { LucideIcon } from "lucide-react";

type Tone = "violet" | "indigo" | "emerald" | "rose";

interface StatCardProps {
  title: string;
  value: string;
  delta: string;
  icon: LucideIcon;
  tone?: Tone;
}

const toneClasses: Record<Tone, string> = {
  violet: "from-violet-500/25 via-violet-500/10 to-transparent text-violet-300",
  indigo: "from-indigo-500/25 via-indigo-500/10 to-transparent text-indigo-300",
  emerald: "from-emerald-500/25 via-emerald-500/10 to-transparent text-emerald-300",
  rose: "from-rose-500/25 via-rose-500/10 to-transparent text-rose-300",
};

export function StatCard({
  title,
  value,
  delta,
  icon: Icon,
  tone = "violet",
}: StatCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.35 }}
      className="relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl"
    >
      <div
        className={`pointer-events-none absolute inset-0 bg-gradient-to-br ${toneClasses[tone]} opacity-60`}
      />

      <div className="relative flex items-start justify-between">
        <div>
          <p className="text-sm text-slate-300/90">{title}</p>
          <p className="mt-3 text-2xl font-semibold text-white">{value}</p>
          <p className="mt-2 text-xs text-slate-300">{delta}</p>
        </div>

        <div className="rounded-xl border border-white/15 bg-white/10 p-2.5">
          <Icon className="h-5 w-5 text-white" />
        </div>
      </div>
    </motion.div>
  );
}
