<?php
/**
 * Site Health test and the public read-only status filter.
 *
 * @package KOZ_Google_Ads_Campaign_Builder
 */

namespace ramirkz\kozgads;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KOZGADS_Health {
	public static function init(): void {
		add_filter( 'site_status_tests', array( __CLASS__, 'register' ) );
		add_filter( 'kozgads_public_status', array( __CLASS__, 'public_status' ) );
	}

	public static function public_status( $value = null ): array {
		return KOZGADS_Plugin::get_status();
	}

	public static function register( array $tests ): array {
		$tests['direct']['kozgads_package'] = array(
			'label' => __( 'Google Ads campaign package', 'koz-google-ads-campaign-builder' ),
			'test'  => array( __CLASS__, 'run' ),
		);
		return $tests;
	}

	public static function run(): array {
		$status   = KOZGADS_Plugin::get_status();
		$problems = array();
		if ( 'ad_grants' === $status['account_mode'] && empty( $status['conversion_confirmed'] ) ) {
			$problems[] = __( 'No real conversion is confirmed for Ad Grants. "Maximize conversions" cannot work without it.', 'koz-google-ads-campaign-builder' );
		}
		$checked = (string) $status['live_destination_checked_at'];
		if ( '' === $checked || strtotime( $checked ) < time() - 30 * DAY_IN_SECONDS ) {
			$problems[] = __( 'The AdsBot destination check has not run in the last 30 days.', 'koz-google-ads-campaign-builder' );
		} elseif ( empty( $status['live_destination_ready'] ) ) {
			$problems[] = __( 'The last AdsBot destination check found unreachable or noindex pages.', 'koz-google-ads-campaign-builder' );
		}
		if ( $status['report_keywords'] > 0 && $status['report_low_volume'] * 2 > $status['report_keywords'] ) {
			$problems[] = __( 'More than half of the imported keywords have low search volume. Generate a new package.', 'koz-google-ads-campaign-builder' );
		}
		$description = '<p>' . esc_html__( 'Checks that the campaign package can actually serve: conversion, landing pages and search demand.', 'koz-google-ads-campaign-builder' ) . '</p>';
		if ( ! empty( $problems ) ) {
			$description .= '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $problems ) ) . '</li></ul>';
		}
		return array(
			'label'       => empty( $problems ) ? __( 'Google Ads package is ready to serve', 'koz-google-ads-campaign-builder' ) : __( 'Google Ads package needs attention', 'koz-google-ads-campaign-builder' ),
			'status'      => empty( $problems ) ? 'good' : 'recommended',
			'badge'       => array( 'label' => __( 'Google Ads', 'koz-google-ads-campaign-builder' ), 'color' => empty( $problems ) ? 'blue' : 'orange' ),
			'description' => $description,
			'actions'     => '<p><a href="' . esc_url( admin_url( 'admin.php?page=koz-google-ads-campaign-builder' ) ) . '">' . esc_html__( 'Open Google Ads Builder', 'koz-google-ads-campaign-builder' ) . '</a></p>',
			'test'        => 'kozgads_package',
		);
	}
}
