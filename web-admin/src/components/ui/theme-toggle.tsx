import { Moon, Sun, Monitor } from 'lucide-react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useThemeContext } from '@/components/layout/ThemeProvider';

export function ThemeToggle() {
  const { theme, setTheme, resolvedTheme } = useThemeContext();

  const Icon =
    theme === 'system'
      ? Monitor
      : resolvedTheme === 'dark'
        ? Moon
        : Sun;

  const label =
    theme === 'system'
      ? 'Tema del sistema'
      : resolvedTheme === 'dark'
        ? 'Tema oscuro'
        : 'Tema claro';

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        className="inline-flex items-center justify-center rounded-md size-9 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors"
      >
        <Icon className="size-4" />
        <span className="sr-only">Cambiar tema ({label})</span>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem onClick={() => setTheme('light')}>
          <Sun className="mr-2 size-4" /> Claro
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => setTheme('dark')}>
          <Moon className="mr-2 size-4" /> Oscuro
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => setTheme('system')}>
          <Monitor className="mr-2 size-4" /> Sistema
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
