import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { TooltipProvider } from '@/components/ui/tooltip';
import { AuthProvider } from '@/context/AuthContext';
import { ThemeProvider } from '@/components/layout/ThemeProvider';
import { BreadcrumbProvider } from '@/context/BreadcrumbContext';
import App from './App';
import './index.css';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ThemeProvider>
      <BreadcrumbProvider>
        <TooltipProvider>
          <AuthProvider>
            <App />
          </AuthProvider>
        </TooltipProvider>
      </BreadcrumbProvider>
    </ThemeProvider>
  </StrictMode>,
);
