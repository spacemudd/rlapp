import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { isRTL } from '@/lib/i18n';

export function useDirection() {
    const { locale } = useI18n();
    
    const isRtl = computed(() => isRTL(locale.value));
    const isLtr = computed(() => !isRTL(locale.value));
    
    // Helper for getting direction-aware classes
    const directionClass = (ltrClass: string, rtlClass: string = '') => {
        return computed(() => isRtl.value ? rtlClass : ltrClass);
    };
    
    // Common direction-aware class utilities
    const marginClasses = {
        // Margin left/right
        ml: (size: string) => directionClass(`ml-${size}`, `mr-${size}`),
        mr: (size: string) => directionClass(`mr-${size}`, `ml-${size}`),
        
        // Padding left/right
        pl: (size: string) => directionClass(`pl-${size}`, `pr-${size}`),
        pr: (size: string) => directionClass(`pr-${size}`, `pl-${size}`),
        
        // Border radius
        roundedL: (size: string = '') => directionClass(`rounded-l${size ? '-' + size : ''}`, `rounded-r${size ? '-' + size : ''}`),
        roundedR: (size: string = '') => directionClass(`rounded-r${size ? '-' + size : ''}`, `rounded-l${size ? '-' + size : ''}`),
        
        // Text alignment
        textLeft: () => directionClass('text-left', 'text-right'),
        textRight: () => directionClass('text-right', 'text-left'),
        
        // Flexbox
        justifyStart: () => directionClass('justify-start', 'justify-end'),
        justifyEnd: () => directionClass('justify-end', 'justify-start'),
        
        // Positioning
        left: (size: string) => directionClass(`left-${size}`, `right-${size}`),
        right: (size: string) => directionClass(`right-${size}`, `left-${size}`),
    };
    
    // Sidebar-specific utilities
    const sidebarSide = computed(() => isRtl.value ? 'right' : 'left');
    const oppositeSidebarSide = computed(() => isRtl.value ? 'left' : 'right');
    
    return {
        isRtl,
        isLtr,
        directionClass,
        marginClasses,
        sidebarSide,
        oppositeSidebarSide,
    };
} 