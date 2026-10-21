import { defineConfig } from 'vitepress'

const title = 'Filament Sanchaya'
const description = 'A focused file manager and media picker for Filament v5.'
const hostname = 'https://filament-sanchaya.dineshphuyel.com.np'

function canonicalUrl(relativePath) {
  const path = relativePath
    .replace(/(^|\/)index\.md$/, '$1')
    .replace(/\.md$/, '')

  return `${hostname}/${path}`
}

function versionSidebar(version) {
  const link = (page = '') => `/${version}/${page}`

  return [
    {
      text: 'Getting Started',
      items: [
        { text: 'Overview', link: link() },
        { text: 'Installation', link: link('installation') },
        { text: 'Configuration', link: link('configuration') },
        { text: 'Plugin Registration', link: link('plugin-registration') },
      ],
    },
    {
      text: 'Security',
      items: [{ text: 'Authorization', link: link('authorization') }],
    },
    {
      text: 'Core UI & Components',
      items: [
        { text: 'File Manager & Bulk Actions', link: link('file-manager') },
        { text: 'Trash Bin & Soft Deletes', link: link('trash') },
        { text: 'SanchayaPicker Field', link: link('sanchaya-picker') },
        { text: 'SanchayaColumn', link: link('sanchaya-column') },
        { text: 'SanchayaEntry', link: link('sanchaya-entry') },
      ],
    },
    {
      text: 'Logic & Data',
      items: [
        { text: 'HasSanchayaFiles Trait', link: link('has-sanchaya-files') },
        { text: 'SanchayaFile & Metadata', link: link('sanchaya-file-model') },
        { text: 'Database Schema', link: link('database-schema') },
      ],
    },
    {
      text: 'Advanced',
      items: [
        ...(version === 'v1.1.0' ? [{ text: 'Chunked Uploads', link: link('chunked-uploads') }] : []),
        { text: 'Built-in Actions', link: link('actions') },
        { text: 'Extensibility', link: link('extensibility') },
        { text: 'Automated Testing', link: link('testing') },
        { text: 'Troubleshooting', link: link('troubleshooting') },
      ],
    },
  ]
}

export default defineConfig({
  title,
  description,
  cleanUrls: true,
  lastUpdated: true,
  sitemap: {
    hostname,
  },
  head: [
    ['meta', { name: 'author', content: 'DP0' }],
    ['meta', { name: 'keywords', content: 'filament-sanchaya, sanchaya, Filament Sanchaya, Filament v5, Filament plugin, Laravel file manager, Laravel media picker, Filament media picker, Livewire media picker, file attachments, storage disk manager, soft deletes, bulk upload, file browser' }],
    ['meta', { property: 'og:image', content: 'https://filament-sanchaya.dineshphuyel.com.np/assets/filament-sanchaya-thumbnail.jpg' }],
    ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
    ['meta', { name: 'twitter:image', content: 'https://filament-sanchaya.dineshphuyel.com.np/assets/filament-sanchaya-thumbnail.jpg' }],
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' }],
    ['link', { rel: 'alternate', type: 'text/plain', href: 'https://filament-sanchaya.dineshphuyel.com.np/llms.txt', title: 'LLMs.txt' }],
    ['link', { rel: 'preconnect', href: 'https://fonts.googleapis.com' }],
    ['link', { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' }],
    ['link', { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600;700&display=swap' }],
  ],
  transformHead({ pageData }) {
    const url = canonicalUrl(pageData.relativePath)
    const pageTitle = pageData.title ? `${pageData.title} | ${title}` : title
    const pageDescription = pageData.description || description

    return [
      ['link', { rel: 'canonical', href: url }],
      ['meta', { property: 'og:type', content: 'website' }],
      ['meta', { property: 'og:url', content: url }],
      ['meta', { property: 'og:title', content: pageTitle }],
      ['meta', { property: 'og:description', content: pageDescription }],
      ['meta', { name: 'twitter:title', content: pageTitle }],
      ['meta', { name: 'twitter:description', content: pageDescription }],
    ]
  },
  themeConfig: {
    logo: {
      light: '/sanchaya-mark.svg',
      dark: '/sanchaya-mark.svg',
      alt: 'Filament Sanchaya',
    },
    nav: [
      { text: 'Docs', link: '/v1.1.0/' },
      {
        text: 'v1.1.x',
        items: [
          { text: 'v1.1.x', link: '/v1.1.0/' },
          { text: 'v1.0.2', link: '/v1.0.2/' },
        ],
      },
    ],
    sidebar: {
      '/v1.1.0/': versionSidebar('v1.1.0'),
      '/v1.0.2/': versionSidebar('v1.0.2'),
    },
    search: {
      provider: 'local',
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/dp-0/filament-sanchaya' },
    ],
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Filament Sanchaya',
    },
  },
})
