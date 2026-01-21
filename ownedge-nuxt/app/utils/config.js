export const SYSTEM_CONFIG = {
    // --- Audio Settings ---
    AUDIO: {
        MASTER_VOL: 0.9,
        // Relative volumes per section
        BOOT_VOL: 0.7,
        ATMOSPHERE_VOL: 0.018,
        MUSIC_VOL: 0.13,
        
        // Music Timing
        MUSIC_START_DELAY: 5500,     // ms
        MUSIC_FADE_IN_DURATION: 5.0, // seconds
        
        // Boot Sound details
        BOOT_SPIN_DURATION: 3.5, // seconds
        BOOT_SEEK_COUNT: 8
    },

    // --- Visual Settings ---
    VISUALS: {
        BRIGHTNESS_DEFAULT: 1,  // Multiplier (0.5 - 1.5)
        CONTRAST_DEFAULT: 1,    // Multiplier (0.5 - 1.5)
        HUE_DEFAULT: 0.5        // 0.0 - 1.0 (0.5 = Teal center)
    }
};
