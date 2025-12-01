<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'O365 Sync - API Testing Dashboard'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="assets/style.css" rel="stylesheet">
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
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
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

<body class="bg-gray-50 dark:bg-gray-900 dark:text-gray-100 h-full overflow-hidden transition-colors duration-300">
    <!-- Top Navbar -->
    <nav
        class="fixed top-0 inset-x-0 h-16 bg-white/90 dark:bg-gray-900/90 backdrop-blur border-b border-gray-200 dark:border-gray-800 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold">
                    O365
                </div>
                <div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">O365 Sync Dashboard</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Admin Workspace</div>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <button onclick="toggleTheme()"
                    class="hidden sm:inline-flex items-center bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600">
                    <svg id="sun-icon" class="w-5 h-5 text-yellow-500 hidden" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z">
                        </path>
                    </svg>
                    <svg id="moon-icon" class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z">
                        </path>
                    </svg>
                    <span id="theme-text" class="ml-2">Dark Mode</span>
                </button>

                <div class="relative">
                    <button id="userMenuButton"
                        class="flex items-center space-x-2 rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600">
                        <span
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-white font-semibold">AD</span>
                        <span class="hidden sm:block">Admin</span>
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                            </path>
                        </svg>
                    </button>
                    <div id="userMenu"
                        class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-2 z-40">
                        <div class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400 uppercase">Account</div>
                        <a href="#"
                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">Profile</a>
                        <a href="#"
                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">Settings</a>
                        <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                        <a href="#"
                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">Sign
                            out</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex h-screen pt-16 bg-gray-50 dark:bg-gray-900">
        <!-- Sidebar -->
        <aside
            class="hidden md:flex w-64 flex-shrink-0 flex-col border-r border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 overflow-y-auto">
            <div class="px-4 py-3">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-500 uppercase tracking-wider mb-3">Menu
                </div>
                <nav class="space-y-1">
                    <a href="#section-overview"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <span class="w-2 h-2 rounded-full bg-green-500 mr-3"></span>Overview
                    </a>
                    <a href="#section-problems"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <span class="w-2 h-2 rounded-full bg-yellow-500 mr-3"></span>Custom Fields
                    </a>
                    <a href="#section-mapping"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <span class="w-2 h-2 rounded-full bg-blue-500 mr-3"></span>Mapping
                    </a>
                    <a href="#section-dashboard"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <span class="w-2 h-2 rounded-full bg-purple-500 mr-3"></span>Sync Status
                    </a>
                    <a href="#section-exceptions"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <span class="w-2 h-2 rounded-full bg-indigo-500 mr-3"></span>Exceptions
                    </a>
                    <a href="#section-debug"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <span class="w-2 h-2 rounded-full bg-gray-400 mr-3"></span>Debug
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Main content -->
        <main class="flex-1 overflow-y-auto">
            <div class="max-w-6xl mx-auto px-4 py-6 space-y-8" id="section-overview">