import { Calendar, MapPin, ShieldCheck, Users } from 'lucide-react';
import type { Rol } from '@/types/usuarios';

export type RolConfig = {
  bg: string;
  fg: string;
  label: string;
  icon: typeof ShieldCheck;
};

const ROL_CONFIG: Record<string, RolConfig> = {
  admin_parametricas: {
    bg: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30',
    fg: 'text-emerald-700 dark:text-emerald-400',
    label: 'Admin. Paramétricas',
    icon: ShieldCheck,
  },
  funcionario_control: {
    bg: 'bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-500/30',
    fg: 'text-sky-700 dark:text-sky-400',
    label: 'Control de Canchas',
    icon: MapPin,
  },
  admin_reservas: {
    bg: 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/30',
    fg: 'text-amber-700 dark:text-amber-400',
    label: 'Admin. Reservas',
    icon: Calendar,
  },
  gerencia: {
    bg: 'bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-200 dark:border-violet-500/30',
    fg: 'text-violet-700 dark:text-violet-400',
    label: 'Gerencia',
    icon: ShieldCheck,
  },
  superadmin: {
    bg: 'bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-200 dark:border-violet-500/30',
    fg: 'text-violet-700 dark:text-violet-400',
    label: 'Superadmin',
    icon: ShieldCheck,
  },
};

const ROL_FALLBACK: RolConfig = {
  bg: 'bg-slate-500/10 text-slate-700 dark:text-slate-400 border-slate-200 dark:border-slate-500/30',
  fg: 'text-slate-700 dark:text-slate-400',
  label: 'Funcionario',
  icon: Users,
};

export function configDeRol(rol: Rol | undefined | null): RolConfig {
  if (!rol) return ROL_FALLBACK;
  return ROL_CONFIG[rol.nombre] ?? { ...ROL_FALLBACK, label: rol.nombre };
}
