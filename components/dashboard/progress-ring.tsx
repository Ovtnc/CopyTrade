"use client";

interface ProgressRingProps {
  label: string;
  value: number;
  hint?: string;
}

const CIRCLE_RADIUS = 42;
const CIRCLE_LENGTH = 2 * Math.PI * CIRCLE_RADIUS;

export function ProgressRing({ label, value, hint }: ProgressRingProps) {
  const safeValue = Math.max(0, Math.min(100, value));
  const dashOffset = CIRCLE_LENGTH - (safeValue / 100) * CIRCLE_LENGTH;

  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-xl">
      <p className="text-sm text-slate-200">{label}</p>

      <div className="mt-4 flex items-center gap-4">
        <div className="relative h-24 w-24">
          <svg className="h-24 w-24 -rotate-90">
            <circle
              cx="48"
              cy="48"
              r={CIRCLE_RADIUS}
              className="fill-none stroke-white/10"
              strokeWidth="8"
            />
            <circle
              cx="48"
              cy="48"
              r={CIRCLE_RADIUS}
              className="fill-none stroke-violet-400 drop-shadow-[0_0_10px_rgba(167,139,250,0.6)]"
              strokeDasharray={CIRCLE_LENGTH}
              strokeDashoffset={dashOffset}
              strokeLinecap="round"
              strokeWidth="8"
            />
          </svg>
          <div className="absolute inset-0 grid place-items-center text-sm font-semibold text-white">
            %{Math.round(safeValue)}
          </div>
        </div>

        <div>
          <p className="text-sm text-slate-300">
            Pipeline health: <span className="text-white">{Math.round(safeValue)}/100</span>
          </p>
          <p className="mt-1 text-xs text-slate-400">{hint ?? "Queue throughput dengeli."}</p>
        </div>
      </div>
    </div>
  );
}
