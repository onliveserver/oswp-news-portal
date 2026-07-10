/**
 * OSWP Post Carousel Block — Editor
 * Supports layouts: card, overlay, ticker, hero
 *
 * @package OSWP News Portal
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	SelectControl,
	TextControl,
	Button,
	BaseControl,
	ColorPalette,
	Icon,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';

/* ================================================================
   Reusable: SpacingControl  (top / right / bottom / left)
   Supports negative values via NumberControl
   ================================================================ */
const SpacingControl = ({ label, values, onChange, min = -200, max = 200 }) => {
	const allEqual =
		values.top === values.right &&
		values.right === values.bottom &&
		values.bottom === values.left;
	const [linked, setLinked] = useState(allEqual);

	const handle = (side, raw) => {
		const num = parseInt(raw, 10) || 0;
		if (linked) {
			onChange({ top: num, right: num, bottom: num, left: num });
		} else {
			onChange({ ...values, [side]: num });
		}
	};

	return (
		<BaseControl label={label} className="oswp-spacing-control">
			<div className="oswp-spacing-control__header">
				<Button
					isSmall
					variant={linked ? 'primary' : 'secondary'}
					icon={linked ? 'admin-links' : 'editor-unlink'}
					onClick={() => setLinked(!linked)}
					label={
						linked
							? __('Unlink sides', 'oswp-posts')
							: __('Link all sides', 'oswp-posts')
					}
				/>
			</div>
			<div className="oswp-spacing-control__inputs">
				{['top', 'right', 'bottom', 'left'].map((s) => (
					<div key={s} className="oswp-spacing-control__field">
						<NumberControl
							label={s.charAt(0).toUpperCase() + s.slice(1)}
							value={values[s]}
							onChange={(v) => handle(s, v)}
							min={min}
							max={max}
							step={1}
						/>
					</div>
				))}
			</div>
		</BaseControl>
	);
};

/* ================================================================
   Reusable: BorderRadiusControl  (TL / TR / BL / BR)
   ================================================================ */
const BorderRadiusControl = ({ values, onChange }) => {
	const allEqual =
		values.tl === values.tr && values.tr === values.bl && values.bl === values.br;
	const [linked, setLinked] = useState(allEqual);

	const handle = (corner, raw) => {
		const num = parseInt(raw, 10) || 0;
		if (linked) {
			onChange({ tl: num, tr: num, bl: num, br: num });
		} else {
			onChange({ ...values, [corner]: num });
		}
	};

	const corners = [
		{ key: 'tl', label: 'TL' },
		{ key: 'tr', label: 'TR' },
		{ key: 'bl', label: 'BL' },
		{ key: 'br', label: 'BR' },
	];

	return (
		<BaseControl
			label={__('Border Radius (px)', 'oswp-posts')}
			className="oswp-border-radius-control"
		>
			<div className="oswp-spacing-control__header">
				<Button
					isSmall
					variant={linked ? 'primary' : 'secondary'}
					icon={linked ? 'admin-links' : 'editor-unlink'}
					onClick={() => setLinked(!linked)}
					label={
						linked
							? __('Unlink corners', 'oswp-posts')
							: __('Link all corners', 'oswp-posts')
					}
				/>
			</div>
			<div className="oswp-spacing-control__inputs">
				{corners.map(({ key, label }) => (
					<div key={key} className="oswp-spacing-control__field">
						<NumberControl
							label={label}
							value={values[key]}
							onChange={(v) => handle(key, v)}
							min={0}
							max={200}
							step={1}
						/>
					</div>
				))}
			</div>
		</BaseControl>
	);
};

/* ================================================================
   Layout definitions
   ================================================================ */
const LAYOUTS = [
	{
		value: 'card',
		label: __('Card', 'oswp-posts'),
		icon: 'grid-view',
		desc: __('Classic card carousel', 'oswp-posts'),
	},
	{
		value: 'overlay',
		label: __('Overlay', 'oswp-posts'),
		icon: 'cover-image',
		desc: __('Image with overlay text', 'oswp-posts'),
	},
	{
		value: 'ticker',
		label: __('Ticker', 'oswp-posts'),
		icon: 'megaphone',
		desc: __('Breaking news ticker', 'oswp-posts'),
	},
	{
		value: 'hero',
		label: __('Hero', 'oswp-posts'),
		icon: 'format-image',
		desc: __('Featured hero layout', 'oswp-posts'),
	},
];

/* ================================================================
   Colour palette presets
   ================================================================ */
const COLORS = [
	{ name: 'Blue', color: '#2563eb' },
	{ name: 'Red', color: '#dc2626' },
	{ name: 'Green', color: '#16a34a' },
	{ name: 'Purple', color: '#9333ea' },
	{ name: 'Orange', color: '#ea580c' },
	{ name: 'Pink', color: '#db2777' },
	{ name: 'Slate', color: '#475569' },
	{ name: 'Black', color: '#000000' },
	{ name: 'White', color: '#ffffff' },
	{ name: 'Gray', color: '#94a3b8' },
];

/* ================================================================
   Block registration
   ================================================================ */
registerBlockType('oswp/post-carousel', {
	edit: ({ attributes, setAttributes }) => {
		const blockProps = useBlockProps();
		const a = attributes;
		const set = setAttributes;

		/* ── Taxonomy data ── */
		const { categories: catRec, tags: tagRec } = useSelect((select) => ({
			categories: select('core').getEntityRecords('taxonomy', 'category', {
				per_page: -1,
			}),
			tags: select('core').getEntityRecords('taxonomy', 'post_tag', {
				per_page: -1,
			}),
		}));
		const catOpts = catRec
			? catRec.map((c) => ({ label: c.name, value: c.id }))
			: [];
		const tagOpts = tagRec
			? tagRec.map((t) => ({ label: t.name, value: t.id }))
			: [];

		/* ── Layout helpers ── */
		const isCard = a.layout === 'card';
		const isOverlay = a.layout === 'overlay';
		const isTicker = a.layout === 'ticker';
		const isHero = a.layout === 'hero';
		const isSlider = isCard || isOverlay;

		return (
			<div {...blockProps}>
				<InspectorControls>
					{/* ──────────── Layout ──────────── */}
					<PanelBody
						title={__('Layout', 'oswp-posts')}
						initialOpen={true}
					>
						<div className="oswp-layout-selector">
							{LAYOUTS.map((l) => (
								<Button
									key={l.value}
									className={`oswp-layout-btn ${a.layout === l.value ? 'is-active' : ''}`}
									onClick={() => set({ layout: l.value })}
								>
									<Icon icon={l.icon} size={24} />
									<span className="oswp-layout-btn__label">
										{l.label}
									</span>
									<span className="oswp-layout-btn__desc">
										{l.desc}
									</span>
								</Button>
							))}
						</div>
					</PanelBody>

					{/* ──────────── Query ──────────── */}
					<PanelBody
						title={__('Query', 'oswp-posts')}
						initialOpen={false}
					>
						<RangeControl
							label={__('Posts to Show', 'oswp-posts')}
							value={a.postsToShow}
							onChange={(v) => set({ postsToShow: v })}
							min={1}
							max={30}
						/>
						<SelectControl
							label={__('Order By', 'oswp-posts')}
							value={a.orderBy}
							options={[
								{ label: 'Date', value: 'date' },
								{ label: 'Title', value: 'title' },
								{ label: 'Random', value: 'rand' },
								{ label: 'Modified', value: 'modified' },
								{
									label: 'Comment Count',
									value: 'comment_count',
								},
							]}
							onChange={(v) => set({ orderBy: v })}
						/>
						<SelectControl
							label={__('Order', 'oswp-posts')}
							value={a.order}
							options={[
								{ label: 'Descending', value: 'desc' },
								{ label: 'Ascending', value: 'asc' },
							]}
							onChange={(v) => set({ order: v })}
						/>
					</PanelBody>

					{/* ──────────── Slider (not ticker) ──────────── */}
					{!isTicker && (
						<PanelBody
							title={__('Slider', 'oswp-posts')}
							initialOpen={false}
						>
							{isSlider && (
								<>
									<RangeControl
										label={__('Slides to Show', 'oswp-posts')}
										value={a.slidesToShow}
										onChange={(v) =>
											set({ slidesToShow: v })
										}
										min={1}
										max={6}
									/>
									<RangeControl
										label={__(
											'Slides to Scroll',
											'oswp-posts'
										)}
										value={a.slidesToScroll}
										onChange={(v) =>
											set({ slidesToScroll: v })
										}
										min={1}
										max={6}
									/>
									<RangeControl
										label={__(
											'Column Gap (px)',
											'oswp-posts'
										)}
										value={a.columnGap}
										onChange={(v) =>
											set({ columnGap: v })
										}
										min={0}
										max={60}
									/>
								</>
							)}

							<ToggleControl
								label={__('Autoplay', 'oswp-posts')}
								checked={a.autoplay}
								onChange={(v) => set({ autoplay: v })}
							/>
							{a.autoplay && (
								<>
									<RangeControl
										label={__(
											'Autoplay Delay (ms)',
											'oswp-posts'
										)}
										value={a.autoplaySpeed}
										onChange={(v) =>
											set({ autoplaySpeed: v })
										}
										min={500}
										max={15000}
										step={100}
									/>
									<ToggleControl
										label={__(
											'Pause on Hover',
											'oswp-posts'
										)}
										checked={a.pauseOnHover}
										onChange={(v) =>
											set({ pauseOnHover: v })
										}
									/>
								</>
							)}

							<RangeControl
								label={__(
									'Transition Speed (ms)',
									'oswp-posts'
								)}
								value={a.speed}
								onChange={(v) => set({ speed: v })}
								min={100}
								max={2000}
								step={50}
							/>
							<ToggleControl
								label={__('Infinite Loop', 'oswp-posts')}
								checked={a.infinite}
								onChange={(v) => set({ infinite: v })}
							/>
							{(isOverlay || isHero) &&
								a.slidesToShow === 1 && (
									<ToggleControl
										label={__(
											'Fade Transition',
											'oswp-posts'
										)}
										checked={a.fade}
										onChange={(v) => set({ fade: v })}
									/>
								)}
						</PanelBody>
					)}

					{/* ──────────── Navigation ──────────── */}
					{!isTicker && (
						<PanelBody
							title={__('Navigation', 'oswp-posts')}
							initialOpen={false}
						>
							{/* Arrows */}
							<ToggleControl
								label={__('Show Arrows', 'oswp-posts')}
								checked={a.arrows}
								onChange={(v) => set({ arrows: v })}
							/>
							{a.arrows && (
								<>
									<SelectControl
										label={__(
											'Arrow Style',
											'oswp-posts'
										)}
										value={a.arrowStyle}
										options={[
											{
												label: 'Default',
												value: 'default',
											},
											{
												label: 'Circle',
												value: 'circle',
											},
											{
												label: 'Square',
												value: 'square',
											},
											{
												label: 'Minimal',
												value: 'minimal',
											},
										]}
										onChange={(v) =>
											set({ arrowStyle: v })
										}
									/>
									<RangeControl
										label={__(
											'Arrow Size (px)',
											'oswp-posts'
										)}
										value={a.arrowSize}
										onChange={(v) =>
											set({ arrowSize: v })
										}
										min={20}
										max={80}
									/>
									<BaseControl
										label={__(
											'Arrow Color',
											'oswp-posts'
										)}
									>
										<ColorPalette
											colors={COLORS}
											value={a.arrowColor}
											onChange={(v) =>
												set({
													arrowColor:
														v || '#1e293b',
												})
											}
										/>
									</BaseControl>
									<BaseControl
										label={__(
											'Arrow Background',
											'oswp-posts'
										)}
									>
										<ColorPalette
											colors={COLORS}
											value={a.arrowBgColor}
											onChange={(v) =>
												set({
													arrowBgColor:
														v || '#ffffff',
												})
											}
										/>
									</BaseControl>
								</>
							)}

							{/* Dots */}
							<ToggleControl
								label={__('Show Dots', 'oswp-posts')}
								checked={a.dots}
								onChange={(v) => set({ dots: v })}
							/>
							{a.dots && (
								<>
									<SelectControl
										label={__(
											'Dots Style',
											'oswp-posts'
										)}
										value={a.dotsStyle}
										options={[
											{
												label: 'Default',
												value: 'default',
											},
											{
												label: 'Line',
												value: 'line',
											},
											{
												label: 'Dash',
												value: 'dash',
											},
										]}
										onChange={(v) =>
											set({ dotsStyle: v })
										}
									/>
									<RangeControl
										label={__(
											'Dots Size (px)',
											'oswp-posts'
										)}
										value={a.dotsSize}
										onChange={(v) =>
											set({ dotsSize: v })
										}
										min={6}
										max={24}
									/>
									<BaseControl
										label={__(
											'Dots Color',
											'oswp-posts'
										)}
									>
										<ColorPalette
											colors={COLORS}
											value={a.dotsColor}
											onChange={(v) =>
												set({
													dotsColor:
														v || '#2563eb',
												})
											}
										/>
									</BaseControl>
									<SelectControl
										label={__(
											'Dots Position',
											'oswp-posts'
										)}
										value={a.dotsPosition}
										options={[
											{
												label: 'Outside',
												value: 'outside',
											},
											{
												label: 'Inside',
												value: 'inside',
											},
										]}
										onChange={(v) =>
											set({ dotsPosition: v })
										}
									/>
								</>
							)}
						</PanelBody>
					)}

					{/* ──────────── Content (not ticker) ──────────── */}
					{!isTicker && (
						<PanelBody
							title={__('Content', 'oswp-posts')}
							initialOpen={false}
						>
							<ToggleControl
								label={__('Show Image', 'oswp-posts')}
								checked={a.showImage}
								onChange={(v) => set({ showImage: v })}
							/>
							<ToggleControl
								label={__('Show Title', 'oswp-posts')}
								checked={a.showTitle}
								onChange={(v) => set({ showTitle: v })}
							/>
							<ToggleControl
								label={__('Show Excerpt', 'oswp-posts')}
								checked={a.showExcerpt}
								onChange={(v) => set({ showExcerpt: v })}
							/>
							{a.showExcerpt && (
								<RangeControl
									label={__(
										'Excerpt Word Limit',
										'oswp-posts'
									)}
									value={a.excerptLimit}
									onChange={(v) =>
										set({ excerptLimit: v })
									}
									min={5}
									max={100}
								/>
							)}
							<ToggleControl
								label={__(
									'Show Read More / Button',
									'oswp-posts'
								)}
								checked={a.showReadMore}
								onChange={(v) => set({ showReadMore: v })}
							/>
							{a.showReadMore && (
								<TextControl
									label={__('Button Text', 'oswp-posts')}
									value={a.readMoreText}
									onChange={(v) =>
										set({ readMoreText: v })
									}
								/>
							)}
							<ToggleControl
								label={__('Show Date', 'oswp-posts')}
								checked={a.showDate}
								onChange={(v) => set({ showDate: v })}
							/>
							<ToggleControl
								label={__('Show Author', 'oswp-posts')}
								checked={a.showAuthor}
								onChange={(v) => set({ showAuthor: v })}
							/>
							<ToggleControl
								label={__('Show Category', 'oswp-posts')}
								checked={a.showCategory}
								onChange={(v) => set({ showCategory: v })}
							/>
						</PanelBody>
					)}

					{/* ──────────── Overlay Settings ──────────── */}
					{isOverlay && (
						<PanelBody
							title={__('Overlay', 'oswp-posts')}
							initialOpen={false}
						>
							<BaseControl
								label={__('Overlay Color', 'oswp-posts')}
							>
								<ColorPalette
									colors={COLORS}
									value={a.overlayColor}
									onChange={(v) =>
										set({ overlayColor: v || '#000000' })
									}
								/>
							</BaseControl>
							<RangeControl
								label={__('Overlay Opacity (%)', 'oswp-posts')}
								value={a.overlayOpacity}
								onChange={(v) => set({ overlayOpacity: v })}
								min={0}
								max={100}
							/>
							<ToggleControl
								label={__(
									'Use Gradient Overlay',
									'oswp-posts'
								)}
								checked={a.overlayGradient}
								onChange={(v) =>
									set({ overlayGradient: v })
								}
							/>
						</PanelBody>
					)}

					{/* ──────────── Ticker Settings ──────────── */}
					{isTicker && (
						<PanelBody
							title={__('Ticker', 'oswp-posts')}
							initialOpen={true}
						>
							<TextControl
								label={__('Label Text', 'oswp-posts')}
								value={a.tickerLabel}
								onChange={(v) => set({ tickerLabel: v })}
							/>
							<BaseControl
								label={__(
									'Label Background',
									'oswp-posts'
								)}
							>
								<ColorPalette
									colors={COLORS}
									value={a.tickerLabelBg}
									onChange={(v) =>
										set({
											tickerLabelBg: v || '#2563eb',
										})
									}
								/>
							</BaseControl>
							<BaseControl
								label={__(
									'Label Text Color',
									'oswp-posts'
								)}
							>
								<ColorPalette
									colors={COLORS}
									value={a.tickerLabelColor}
									onChange={(v) =>
										set({
											tickerLabelColor:
												v || '#ffffff',
										})
									}
								/>
							</BaseControl>
							<BaseControl
								label={__(
									'Ticker Background',
									'oswp-posts'
								)}
							>
								<ColorPalette
									colors={COLORS}
									value={a.tickerBgColor}
									onChange={(v) =>
										set({
											tickerBgColor: v || '#f8fafc',
										})
									}
								/>
							</BaseControl>
							<BaseControl
								label={__(
									'Ticker Text Color',
									'oswp-posts'
								)}
							>
								<ColorPalette
									colors={COLORS}
									value={a.tickerTextColor}
									onChange={(v) =>
										set({
											tickerTextColor:
												v || '#1e293b',
										})
									}
								/>
							</BaseControl>
							<RangeControl
								label={__(
									'Ticker Speed (ms)',
									'oswp-posts'
								)}
								value={a.tickerSpeed}
								onChange={(v) => set({ tickerSpeed: v })}
								min={1000}
								max={20000}
								step={500}
							/>
						</PanelBody>
					)}

					{/* ──────────── Hero Settings ──────────── */}
					{isHero && (
						<PanelBody
							title={__('Hero', 'oswp-posts')}
							initialOpen={false}
						>
							<SelectControl
								label={__(
									'Layout Direction',
									'oswp-posts'
								)}
								value={a.heroLayout}
								options={[
									{
										label: 'Text Left / Image Right',
										value: 'text-left',
									},
									{
										label: 'Text Right / Image Left',
										value: 'text-right',
									},
								]}
								onChange={(v) => set({ heroLayout: v })}
							/>
							<ToggleControl
								label={__('Show Live Badge', 'oswp-posts')}
								checked={a.showLiveBadge}
								onChange={(v) =>
									set({ showLiveBadge: v })
								}
							/>
							{a.showLiveBadge && (
								<>
									<TextControl
										label={__(
											'Badge Text',
											'oswp-posts'
										)}
										value={a.liveBadgeText}
										onChange={(v) =>
											set({ liveBadgeText: v })
										}
									/>
									<BaseControl
										label={__(
											'Badge Color',
											'oswp-posts'
										)}
									>
										<ColorPalette
											colors={COLORS}
											value={a.liveBadgeColor}
											onChange={(v) =>
												set({
													liveBadgeColor:
														v || '#dc2626',
												})
											}
										/>
									</BaseControl>
								</>
							)}
							<ToggleControl
								label={__('Show Time Ago', 'oswp-posts')}
								checked={a.showTimeAgo}
								onChange={(v) => set({ showTimeAgo: v })}
							/>
							<ToggleControl
								label={__(
									'Show Image Counter',
									'oswp-posts'
								)}
								checked={a.showImageCounter}
								onChange={(v) =>
									set({ showImageCounter: v })
								}
							/>
						</PanelBody>
					)}

					{/* ──────────── Dimensions ──────────── */}
					<PanelBody
						title={__('Dimensions', 'oswp-posts')}
						initialOpen={false}
					>
						{!isTicker && (
							<>
								<NumberControl
									label={__(
										'Slider Height (px, 0 = auto)',
										'oswp-posts'
									)}
									value={a.sliderHeight}
									onChange={(v) =>
										set({
											sliderHeight:
												parseInt(v, 10) || 0,
										})
									}
									min={0}
									max={1000}
								/>
								<NumberControl
									label={__(
										'Image Height (px, 0 = auto)',
										'oswp-posts'
									)}
									value={a.imageHeight}
									onChange={(v) =>
										set({
											imageHeight:
												parseInt(v, 10) || 0,
										})
									}
									min={0}
									max={800}
								/>
							</>
						)}
					</PanelBody>

					{/* ──────────── Spacing ──────────── */}
					{!isTicker && (
						<PanelBody
							title={__('Spacing', 'oswp-posts')}
							initialOpen={false}
						>
							<SpacingControl
								label={__(
									'Card Padding (px)',
									'oswp-posts'
								)}
								values={{
									top: a.paddingTop,
									right: a.paddingRight,
									bottom: a.paddingBottom,
									left: a.paddingLeft,
								}}
								onChange={(v) =>
									set({
										paddingTop: v.top,
										paddingRight: v.right,
										paddingBottom: v.bottom,
										paddingLeft: v.left,
									})
								}
								min={-100}
								max={200}
							/>
							<SpacingControl
								label={__(
									'Card Margin (px)',
									'oswp-posts'
								)}
								values={{
									top: a.marginTop,
									right: a.marginRight,
									bottom: a.marginBottom,
									left: a.marginLeft,
								}}
								onChange={(v) =>
									set({
										marginTop: v.top,
										marginRight: v.right,
										marginBottom: v.bottom,
										marginLeft: v.left,
									})
								}
								min={-100}
								max={200}
							/>
						</PanelBody>
					)}

					{/* ──────────── Border ──────────── */}
					{!isTicker && (
						<PanelBody
							title={__('Border', 'oswp-posts')}
							initialOpen={false}
						>
							<RangeControl
								label={__(
									'Border Width (px)',
									'oswp-posts'
								)}
								value={a.borderWidth}
								onChange={(v) => set({ borderWidth: v })}
								min={0}
								max={20}
							/>
							<SelectControl
								label={__('Border Style', 'oswp-posts')}
								value={a.borderStyle}
								options={[
									{ label: 'None', value: 'none' },
									{ label: 'Solid', value: 'solid' },
									{ label: 'Dashed', value: 'dashed' },
									{ label: 'Dotted', value: 'dotted' },
									{ label: 'Double', value: 'double' },
								]}
								onChange={(v) => set({ borderStyle: v })}
							/>
							<BaseControl
								label={__('Border Color', 'oswp-posts')}
							>
								<ColorPalette
									colors={COLORS}
									value={a.borderColor}
									onChange={(v) =>
										set({
											borderColor: v || '#e2e8f0',
										})
									}
								/>
							</BaseControl>
							<BorderRadiusControl
								values={{
									tl: a.borderRadiusTL,
									tr: a.borderRadiusTR,
									bl: a.borderRadiusBL,
									br: a.borderRadiusBR,
								}}
								onChange={(v) =>
									set({
										borderRadiusTL: v.tl,
										borderRadiusTR: v.tr,
										borderRadiusBL: v.bl,
										borderRadiusBR: v.br,
									})
								}
							/>
						</PanelBody>
					)}

					{/* ──────────── Card Style ──────────── */}
					{isCard && (
						<PanelBody
							title={__('Card Style', 'oswp-posts')}
							initialOpen={false}
						>
							<SelectControl
								label={__('Shadow', 'oswp-posts')}
								value={a.cardShadow}
								options={[
									{ label: 'None', value: 'none' },
									{ label: 'Small', value: 'small' },
									{ label: 'Medium', value: 'medium' },
									{ label: 'Large', value: 'large' },
									{
										label: 'Extra Large',
										value: 'xlarge',
									},
								]}
								onChange={(v) => set({ cardShadow: v })}
							/>
							<ToggleControl
								label={__('Hover Effect', 'oswp-posts')}
								checked={a.hoverEffect}
								onChange={(v) => set({ hoverEffect: v })}
							/>
						</PanelBody>
					)}

					{/* ──────────── Typography & Colors ──────────── */}
					<PanelBody
						title={__('Typography & Colors', 'oswp-posts')}
						initialOpen={false}
					>
						{!isTicker && (
							<>
								<RangeControl
									label={__(
										'Title Font Size (px)',
										'oswp-posts'
									)}
									value={a.titleFontSize}
									onChange={(v) =>
										set({ titleFontSize: v })
									}
									min={10}
									max={60}
								/>
								<BaseControl
									label={__(
										'Title Color',
										'oswp-posts'
									)}
								>
									<ColorPalette
										colors={COLORS}
										value={a.titleColor}
										onChange={(v) =>
											set({
												titleColor:
													v || '#1e293b',
											})
										}
									/>
								</BaseControl>
								<RangeControl
									label={__(
										'Excerpt Font Size (px)',
										'oswp-posts'
									)}
									value={a.excerptFontSize}
									onChange={(v) =>
										set({ excerptFontSize: v })
									}
									min={10}
									max={24}
								/>
								<BaseControl
									label={__(
										'Excerpt Color',
										'oswp-posts'
									)}
								>
									<ColorPalette
										colors={COLORS}
										value={a.excerptColor}
										onChange={(v) =>
											set({
												excerptColor:
													v || '#64748b',
											})
										}
									/>
								</BaseControl>
								<RangeControl
									label={__(
										'Meta Font Size (px)',
										'oswp-posts'
									)}
									value={a.metaFontSize}
									onChange={(v) =>
										set({ metaFontSize: v })
									}
									min={8}
									max={20}
								/>
								<BaseControl
									label={__(
										'Meta Color',
										'oswp-posts'
									)}
								>
									<ColorPalette
										colors={COLORS}
										value={a.metaColor}
										onChange={(v) =>
											set({
												metaColor: v || '#94a3b8',
											})
										}
									/>
								</BaseControl>
							</>
						)}
						<BaseControl
							label={__('Background Color', 'oswp-posts')}
						>
							<ColorPalette
								colors={COLORS}
								value={a.backgroundColor}
								onChange={(v) =>
									set({ backgroundColor: v || '#ffffff' })
								}
							/>
						</BaseControl>
						{!isTicker && (
							<BaseControl
								label={__(
									'Category Badge Color',
									'oswp-posts'
								)}
							>
								<ColorPalette
									colors={COLORS}
									value={a.categoryBgColor}
									onChange={(v) =>
										set({
											categoryBgColor:
												v || '#2563eb',
										})
									}
								/>
							</BaseControl>
						)}
					</PanelBody>

					{/* ──────────── Button Style ──────────── */}
					{!isTicker && a.showReadMore && (
						<PanelBody
							title={__('Button Style', 'oswp-posts')}
							initialOpen={false}
						>
							<BaseControl
								label={__('Button Color', 'oswp-posts')}
							>
								<ColorPalette
									colors={COLORS}
									value={a.buttonColor}
									onChange={(v) =>
										set({
											buttonColor: v || '#ffffff',
										})
									}
								/>
							</BaseControl>
							<BaseControl
								label={__(
									'Button Background',
									'oswp-posts'
								)}
							>
								<ColorPalette
									colors={COLORS}
									value={a.buttonBgColor}
									onChange={(v) =>
										set({
											buttonBgColor: v || '#2563eb',
										})
									}
								/>
							</BaseControl>
							<RangeControl
								label={__(
									'Button Border Radius (px)',
									'oswp-posts'
								)}
								value={a.buttonBorderRadius}
								onChange={(v) =>
									set({ buttonBorderRadius: v })
								}
								min={0}
								max={50}
							/>
							<RangeControl
								label={__(
									'Button Font Size (px)',
									'oswp-posts'
								)}
								value={a.buttonFontSize}
								onChange={(v) =>
									set({ buttonFontSize: v })
								}
								min={10}
								max={24}
							/>
						</PanelBody>
					)}
				</InspectorControls>

				{/* ──────────── Preview ──────────── */}
				<div className="oswp-post-carousel-editor">
					<ServerSideRender
						block="oswp/post-carousel"
						attributes={a}
					/>
				</div>
			</div>
		);
	},
});
