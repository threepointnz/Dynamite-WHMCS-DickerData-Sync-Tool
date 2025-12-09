<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'O365 Sync - API Testing Dashboard'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <script>
        // Theme detection and management
        function getThemePreference() {
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                return 'dark';
            } else {
                return 'light';
            }
        }

        function setTheme(theme) {
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            }
            updateThemeToggle();
        }

        function toggleTheme() {
            const currentTheme = getThemePreference();
            setTheme(currentTheme === 'dark' ? 'light' : 'dark');
        }

        function updateThemeToggle() {
            const isDark = getThemePreference() === 'dark';
            const sunIcon = document.getElementById('sun-icon');
            const moonIcon = document.getElementById('moon-icon');
            const themeText = document.getElementById('theme-text');

            if (sunIcon && moonIcon && themeText) {
                if (isDark) {
                    sunIcon.classList.remove('hidden');
                    moonIcon.classList.add('hidden');
                    themeText.textContent = 'Light Mode';
                } else {
                    sunIcon.classList.add('hidden');
                    moonIcon.classList.remove('hidden');
                    themeText.textContent = 'Dark Mode';
                }
            }
        }

        // Set theme immediately to prevent flash
        setTheme(getThemePreference());

        // Listen for OS theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
            if (!('theme' in localStorage)) {
                setTheme(e.matches ? 'dark' : 'light');
            }
        });

        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#1e40af'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 dark:bg-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-300">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-2">O365 Sync Dashboard</h1>
                <p class="text-gray-600 dark:text-gray-300">API Testing & Configuration</p>
            </div>

            <!-- Theme Toggle Button -->
            <button onclick="toggleTheme()"
                class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg p-3 shadow-sm hover:shadow-md transition-all duration-200 flex items-center space-x-2">
                <!-- Sun Icon (for light mode) -->
                <svg id="sun-icon" class="w-5 h-5 text-yellow-500 hidden" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z">
                    </path>
                </svg>

                <!-- Moon Icon (for dark mode) -->
                <svg id="moon-icon" class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z">
                    </path>
                </svg>

                <span id="theme-text" class="text-sm font-medium text-gray-700 dark:text-gray-300">Dark Mode</span>
            </button>
        </div>