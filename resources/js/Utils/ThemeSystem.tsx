import { useEffect, useMemo, useState } from 'react';

export type Skin = 'light' | 'dark';
export type Palette = {
  primary: string; secondary: string; accent: string; background: string; surface: string;
  text: string; muted: string; border: string; success: string; warning: string; danger: string;
};

export type ThemeDefinition = { key: string; name: string; description: string; light: Palette; dark: Palette };

const p = (primary: string, secondary: string, accent: string, light: Omit<Palette, 'primary'|'secondary'|'accent'>, dark: Omit<Palette, 'primary'|'secondary'|'accent'>): ThemeDefinition => ({
  key: '', name: '', description: '', light: { primary, secondary, accent, ...light }, dark: { primary, secondary, accent, ...dark },
});

const commonLight = { background: '#F7F9FC', surface: '#FFFFFF', text: '#142235', muted: '#64748B', border: '#E2E8F0', success: '#16A34A', warning: '#D97706', danger: '#DC2626' };
const commonDark = { background: '#0B1020', surface: '#121A2A', text: '#F8FAFC', muted: '#94A3B8', border: '#263247', success: '#4ADE80', warning: '#FBBF24', danger: '#F87171' };

export const THEMES: ThemeDefinition[] = [
  { ...p('#2563EB','#101B3D','#14B8A6',
    {...commonLight,background:'#F4F7FC',surface:'#FFFFFF',text:'#101B3D',muted:'#64748B',border:'#DFE7F2'},
    {...commonDark,background:'#071126',surface:'#101D35',text:'#F8FAFC',muted:'#A8B8D0',border:'#243653'}),
    key:'semizzy-signature', name:'SEMIZZY Signature', description:'Shared Midnight Navy, Electric Blue and Teal brand for Fresh Fintech and Enterprise Control' },
  { ...p('#10B981','#047857','#34D399',commonLight,{...commonDark,background:'#071A16',surface:'#0D2720'}), key:'opay-inspired', name:'Emerald Fintech', description:'Fresh Nigerian fintech energy with a polished, trustworthy green identity' },
  { ...p('#7C3AED','#5B21B6','#A78BFA',commonLight,{...commonDark,background:'#160C2B',surface:'#21133D'}), key:'palmpay-inspired', name:'Royal Purple', description:'Bold consumer-fintech personality with premium purple accents' },
  { ...p('#1D4ED8','#1E3A8A','#38BDF8',commonLight,{...commonDark,background:'#07142B',surface:'#10213D'}), key:'modern-corporate', name:'Ocean Corporate', description:'Professional blue enterprise theme built for trust and clarity' },
  { ...p('#4F46E5','#3730A3','#06B6D4',commonLight,{...commonDark,background:'#0D1024',surface:'#171A35'}), key:'clean-saas', name:'Indigo Tech', description:'Modern digital-platform look with clean hierarchy and bright accents' },
  { ...p('#111827','#1F2937','#D4AF37',{...commonLight,background:'#FAF9F6',surface:'#FFFFFF',text:'#111827',muted:'#6B7280',border:'#E5E7EB'}, {...commonDark,background:'#090A0D',surface:'#141519',text:'#F9FAFB'}), key:'luxury-executive', name:'Midnight Gold', description:'Distinctive premium business aesthetic with restrained gold highlights' },
  { ...p('#0EA5E9','#0369A1','#22D3EE',commonLight,{...commonDark,background:'#061827',surface:'#0C2234'}), key:'sky-enterprise', name:'Sky Enterprise', description:'Clean enterprise blue with a modern digital-services feel' },
  { ...p('#059669','#065F46','#6EE7B7',commonLight,{...commonDark,background:'#061B15',surface:'#0D2A21'}), key:'forest-growth', name:'Forest Growth', description:'Balanced green identity for savings, rewards and growth products' },
  { ...p('#E11D48','#9F1239','#FB7185',commonLight,{...commonDark,background:'#240B13',surface:'#35111C'}), key:'crimson-modern', name:'Crimson Modern', description:'Confident red-accented professional fintech theme' },
  { ...p('#F97316','#C2410C','#FDBA74',commonLight,{...commonDark,background:'#241106',surface:'#34190B'}), key:'sunset-commerce', name:'Sunset Commerce', description:'Warm commerce-focused palette with strong conversion accents' },
  { ...p('#334155','#0F172A','#94A3B8',commonLight,{...commonDark,background:'#080D16',surface:'#111827'}), key:'slate-professional', name:'Slate Professional', description:'Neutral executive interface optimized for dense administration screens' },
];

export const DEFAULT_CUSTOM: Record<Skin, Palette> = {
  light: { ...commonLight, primary:'#2563EB', secondary:'#1D4ED8', accent:'#38BDF8' },
  dark: { ...commonDark, primary:'#60A5FA', secondary:'#3B82F6', accent:'#38BDF8' },
};

const validHex = (v: unknown, fallback: string) => typeof v === 'string' && /^#[0-9A-Fa-f]{6}$/.test(v) ? v : fallback;

export function normalizePalette(value: Partial<Palette> | null | undefined, fallback: Palette): Palette {
  return {
    primary: validHex(value?.primary, fallback.primary), secondary: validHex(value?.secondary, fallback.secondary),
    accent: validHex(value?.accent, fallback.accent), background: validHex(value?.background, fallback.background),
    surface: validHex(value?.surface, fallback.surface), text: validHex(value?.text, fallback.text),
    muted: validHex(value?.muted, fallback.muted), border: validHex(value?.border, fallback.border),
    success: validHex(value?.success, fallback.success), warning: validHex(value?.warning, fallback.warning),
    danger: validHex(value?.danger, fallback.danger),
  };
}

export function getTheme(key: string): ThemeDefinition {
  return THEMES.find(t => t.key === key) ?? THEMES[0];
}

function contrast(hex: string): number {
  const n = hex.replace('#',''); const rgb = [0,2,4].map(i => parseInt(n.slice(i,i+2),16)/255);
  const lum = rgb.map(c => c <= .03928 ? c/12.92 : Math.pow((c+.055)/1.055,2.4));
  return 0.2126*lum[0]+0.7152*lum[1]+0.0722*lum[2];
}
export function contrastRatio(a: string, b: string): number {
  const l1=contrast(a), l2=contrast(b); return (Math.max(l1,l2)+.05)/(Math.min(l1,l2)+.05);
}

export function applyTheme(themeKey: string, skin: Skin, custom?: Record<Skin, Partial<Palette>>) {
  const theme = getTheme(themeKey);
  const base = themeKey === 'custom' ? normalizePalette(custom?.[skin], DEFAULT_CUSTOM[skin]) : theme[skin];
  const root = document.documentElement;
  root.dataset.theme = themeKey;
  root.dataset.skin = skin;
  Object.entries(base).forEach(([key,value]) => root.style.setProperty('--so-' + key, value));
  root.style.setProperty('--so-primary-soft', 'color-mix(in srgb, var(--so-primary) 10%, var(--so-surface))');
  root.style.setProperty('--so-primary-medium', 'color-mix(in srgb, var(--so-primary) 18%, var(--so-surface))');
  root.style.setProperty('--so-primary-dark', 'color-mix(in srgb, var(--so-primary) 82%, #000)');
  root.style.colorScheme = skin;
}

export function ThemeControls({ defaultSkin = 'light', themeKey = 'semizzy-signature', custom }: { defaultSkin?: Skin; themeKey?: string; custom?: Record<Skin, Partial<Palette>> }) {
  const [skin,setSkin] = useState<Skin>(() => {
    if (typeof window === 'undefined') return defaultSkin;
    const saved=window.localStorage.getItem('semizzy.skin'); return saved === 'dark' || saved === 'light' ? saved : defaultSkin;
  });
  useEffect(() => { applyTheme(themeKey,skin,custom); window.localStorage.setItem('semizzy.skin',skin); }, [themeKey,skin,custom]);
  return <button type="button" onClick={() => setSkin(s => s === 'light' ? 'dark' : 'light')} aria-label={skin === 'light' ? 'Switch to dark skin' : 'Switch to light skin'} title={skin === 'light' ? 'Switch to dark skin' : 'Switch to light skin'} className="so-skin-switch"><span aria-hidden="true">{skin === 'light' ? '☀' : '☾'}</span><span>{skin === 'light' ? 'Light' : 'Dark'}</span></button>;
}

export function ThemeBridge({ children, platform }: { children: React.ReactNode; platform?: { theme_key?: string; theme_custom_light?: Partial<Palette>; theme_custom_dark?: Partial<Palette>; skin_default?: Skin } }) {
  const themeKey = platform?.theme_key ?? 'modern-corporate';
  const custom = useMemo(() => ({ light: platform?.theme_custom_light ?? {}, dark: platform?.theme_custom_dark ?? {} }), [platform?.theme_custom_light, platform?.theme_custom_dark]);
  const [skin,setSkin] = useState<Skin>(() => {
    if (typeof window === 'undefined') return platform?.skin_default === 'dark' ? 'dark' : 'light';
    const saved=window.localStorage.getItem('semizzy.skin'); return saved === 'dark' || saved === 'light' ? saved : (platform?.skin_default === 'dark' ? 'dark' : 'light');
  });
  useEffect(() => { applyTheme(themeKey,skin,custom); window.localStorage.setItem('semizzy.skin',skin); }, [themeKey,skin,custom]);
  return <><div className="so-global-skin-control"><button type="button" onClick={() => setSkin(s => s === 'light' ? 'dark' : 'light')} className="so-skin-switch" aria-label="Toggle light and dark skin"><span>{skin === 'light' ? '☀' : '☾'}</span><span>{skin === 'light' ? 'Light' : 'Dark'}</span></button></div>{children}</>;
}