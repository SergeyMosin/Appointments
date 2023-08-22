<template>
	<transition name="slide-right">
		<aside class="app-slidebar">
			<header :class="{'app-sidebar-header--with-figure': hasFigure}"
							class="app-sidebar-header">
				<!-- close sidebar button -->
				<a href="#" :class="['app-sidebar__close', icon]" :title="t('appointments','close')"
					 @click.prevent="closeSidebar"/>

				<!-- sidebar header illustration/figure -->
				<div v-if="hasFigure" :class="{
						'app-sidebar-header__figure--with-action': hasFigureClickListener
					}" class="app-sidebar-header__figure"
						 :style="{
						backgroundImage: `url(${background})`
					}"
						 @click="onFigureClick">
					<slot class="app-sidebar-header__background" name="header"/>
				</div>

				<!-- sidebar details -->
				<div :class="{'app-sidebar-header__desc--with-subtitle': subtitle}" class="app-sidebar-header__desc">
					<!-- main title -->
					<h2 class="app-sidebar-header__title">
						{{ title }}
					</h2>
					<!-- secondary title -->
					<p v-if="subtitle.trim() !== ''" class="app-sidebar-header__subtitle">
						{{ subtitle }}
					</p>
				</div>
				<div v-if="$slots['primary-actions']" class="app-sidebar-header__action">
					<slot name="primary-actions"/>
				</div>
			</header>
			<div class="app-slidebar-main-area">
				<slot name="main-area"/>
			</div>
		</aside>
	</transition>
</template>

<script>
export default {
	name: "SlideBar",
	props: {
		title: {
			type: String,
			default: '',
			required: true
		},
		titlePlaceholder: {
			type: String,
			default: ''
		},
		subtitle: {
			type: String,
			default: ''
		},
		icon: {
			type: String,
			default: 'icon-close'
		},
		/**
		 * Url to the top header background image
		 * Applied with css
		 */
		background: {
			type: String,
			default: ''
		}
	},
	computed: {
		hasFigure() {
			return this.$slots['header'] || this.background
		},
		hasFigureClickListener() {
			return this.$listeners['figure-click']
		},
	},
	methods: {
		/**
		 * Emit sidebar close event to parent component
		 */
		closeSidebar(e) {
			this.$emit('close', e)
		},

		/**
		 * Emit figure click event to parent component
		 */
		onFigureClick(e) {
			this.$emit('figure-click', e)
		},
	}
}


</script>

<style lang="scss" scoped>
@use "sass:math";
@import '../../scss/_variables';

$slidebar-min-width: 360px;
$sidebar-max-width: 500px;

$desc-vertical-padding: 18px;
$desc-input-padding: 7px;
$desc-title-height: 30px;
// title and subtitle
$desc-height: $desc-title-height + 22px;

$top-buttons-spacing: 6px;

.app-slidebar {
	z-index: 1500;
	height: 100%;
	width: 27vw;
	min-width: $slidebar-min-width;
	max-width: $sidebar-max-width;
	top: 0;

	left: auto;
	right: auto;

	display: flex;
	flex-shrink: 0;
	flex-direction: column;
	/*position: -webkit-sticky; // Safari support*/
	position: absolute;

	overflow-y: auto;
	overflow-x: hidden;

	background: var(--color-main-background);
	border-right: 1px solid var(--color-border);

	.app-slidebar-main-area {
		text-align: center;
		//noinspection CssInvalidFunction
		padding: #{$desc-vertical-padding} math.div($desc-vertical-padding, 2) #{$desc-vertical-padding} math.div($desc-vertical-padding, 2);
	}


	.app-sidebar-header {
		> .app-sidebar__close {
			position: absolute;
			width: $clickable-area;
			height: $clickable-area;
			top: $top-buttons-spacing;
			right: $top-buttons-spacing;
			z-index: 100;
			opacity: $opacity_normal;
			//noinspection CssInvalidFunction
			border-radius: math.div($clickable-area, 2);

			&:hover,
			&:active,
			&:focus {
				opacity: $opacity_full;
				background-color: $action-background-hover;
			}
		}

		// header background
		&__figure {
			max-height: 250px;
			height: 250px;
			width: 100%;
			background-size: contain;
			background-position: center;
			background-repeat: no-repeat;

			&--with-action {
				cursor: pointer;
			}
		}

		&__desc {
			position: relative;
			//noinspection CssInvalidFunction
			padding: #{$desc-vertical-padding} #{$clickable-area + $top-buttons-spacing * 3} #{$desc-vertical-padding} math.div($desc-vertical-padding, 2);
			display: flex;
			flex-direction: column;
			justify-content: center;
			box-sizing: content-box;

			// titles
			.app-sidebar-header__title,
			.app-sidebar-header__subtitle {
				width: 100%;
				white-space: nowrap;
				text-overflow: ellipsis;
				overflow: hidden;
				margin: 0;
			}

			// main title
			.app-sidebar-header__title {
				padding: 0;
				font-size: 20px;
				line-height: $desc-title-height;
			}

			// subtitle
			.app-sidebar-header__subtitle {
				font-size: 14px;
				padding: 0;
				opacity: $opacity_normal;
			}

			// main menu
			//noinspection CssInvalidFunction
			.app-sidebar-header__menu {
				position: absolute;
				right: math.div($clickable-area, 2);
				background-color: $action-background-hover;
				border-radius: math.div($clickable-area, 2);
			}

			&--with-subtitle {
				justify-content: space-between;
				height: $desc-height;
			}
		}

		&--with-figure {
			.app-sidebar-header__desc {
				padding-right: $clickable-area * 2;
			}
		}

		&:not(.app-sidebar-header--with-figure) {
			.app-sidebar-header__menu {
				top: $top-buttons-spacing;
				right: $top-buttons-spacing * 2 + $clickable-area;
			}
		}
	}
}

.slide-right-leave-active,
.slide-right-enter-active {
	transition-duration: var(--animation-quick);
	transition-property: max-width, min-width;
}

.slide-right-enter-to,
.slide-right-leave {
	min-width: $slidebar-min-width;
	max-width: $sidebar-max-width;
}

.slide-right-enter,
.slide-right-leave-to {
	min-width: 0 !important;
	max-width: 0 !important;
}

.fade-leave-active,
.fade-enter-active {
	transition-duration: var(--animation-quick);
	transition-property: opacity;
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	opacity: $opacity_full;
}

.fade-enter,
.fade-leave-to {
	opacity: 0;
}

@media only screen and (max-width: $breakpoint-mobile) {
	.app-slidebar {
		border-right: none;
		border-left: 1px solid var(--color-border);
		position: absolute;
		right: 0;
		left: auto;
	}
}
</style>
