<?php

use Statamic\Facades\Stache;

beforeEach(function () {
    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->mp4->set('mux', ['id' => 123, 'playback_ids' => ['public' => 456]]);
    $this->mp4->save();

    Stache::clear();
});

test('throws when missing variable in wildcard tag', function () {
    expect(fn () => $this->antlers('{{ mux:something }}'))->toThrow('Variable [something] does not exist in context.');
});

test('renders video component', function () {
    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" }}')
        ->assertSeeInOrder([
            '<mux-video',
            'playback-id="456"',
            '></mux-video>',
        ], false);
});

test('applies params to video component', function () {
    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" }}')
        ->assertDontSee('autoplay');

    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" autoplay="true" loop="true" }}')
        ->assertSeeInOrder([
            '<mux-video',
            'autoplay',
            'loop',
        ], false)
        ->assertDontSee('muted');
});

test('embeds video scripts', function () {
    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" }}')
        ->assertDontSee('<script async src="https://unpkg.com/@mux/mux-video@0"></script>', false);

    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" script="true" }}')
        ->assertSee('<script async src="https://unpkg.com/@mux/mux-video@0"></script>', false);
});

test('lazyloads video scripts', function () {
    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" }}')
        ->assertDontSee('<is-land', false);

    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" script="true" lazyload="true" }}')
        ->assertSeeInOrder([
            'https://unpkg.com/@11ty/is-land',
            '<is-land on:visible>',
            '<script async src="https://unpkg.com/@mux/mux-video@0"></script>',
            '</is-land>',
        ], false);
});

test('renders player component', function () {
    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" }}')
        ->assertSeeInOrder([
            '<mux-player',
            'playback-id="456"',
            '></mux-player>',
        ], false);
});

test('applies params to player component', function () {
    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" }}')
        ->assertDontSee('autoplay');

    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" autoplay="true" loop="true" }}')
        ->assertSeeInOrder([
            '<mux-player',
            'autoplay',
            'loop',
        ], false)
        ->assertDontSee('muted');

    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" accent-color="#f00" }}')
        ->assertSeeInOrder([
            '<mux-player',
            'accent-color="#f00"',
        ], false);
});

test('embeds player scripts', function () {
    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" }}')
        ->assertDontSee('<script async src="https://unpkg.com/@mux/mux-player@3"></script>', false);

    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" script="true" }}')
        ->assertSee('<script async src="https://unpkg.com/@mux/mux-player@3"></script>', false);
});

test('lazyloads player scripts', function () {
    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" }}')
        ->assertDontSee('<is-land', false);

    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" script="true" lazyload="true" }}')
        ->assertSeeInOrder([
            'https://unpkg.com/@11ty/is-land',
            '<is-land on:visible>',
            '<script async src="https://unpkg.com/@mux/mux-player@3"></script>',
            '</is-land>',
        ], false);
});

test('renders iframe embed', function () {
    $this->antlers('{{ mux:embed src="test_container_assets::test.mp4" }}')
        ->assertSeeInOrder([
            '<iframe',
            'src="https://player.mux.com/456"',
            '></iframe>',
        ], false);
});

test('applies params to embed component', function () {
    $this->antlers('{{ mux:embed src="test_container_assets::test.mp4" }}')
        ->assertDontSee('autoplay');

    $this->antlers('{{ mux:embed src="test_container_assets::test.mp4" autoplay="true" loop="true" }}')
        ->assertSeeInOrder([
            '<iframe',
            'src="https://player.mux.com/456?autoplay=1&loop=1"',
        ], false)
        ->assertDontSee('muted');

    $this->antlers('{{ mux:embed src="test_container_assets::test.mp4" disable-tracking="true" }}')
        ->assertSeeInOrder([
            '<iframe',
            'src="https://player.mux.com/456?disable-tracking=1"',
        ], false);
});
