<script setup>
  import DefaultTheme from 'vitepress/theme'
  import Image from '../assets/video.png'

  const { Layout } = DefaultTheme
</script>

<template>
  <Layout>
    <template #home-hero-image>
      <div class="home-hero-image">
        <img :src="Image" alt="Video" />
      </div>
    </template>
  </Layout>
</template>

<style scoped>
  div {
    margin: 0 16px;
  }
  img {
    width: 100%;
    max-width: 24em;
    height: auto;
    border-radius: 10px;
  }
</style>
