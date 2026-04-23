<?php
defined('ABSPATH') || exit;

$email_show_footer_html = isset($email_footer_html) && is_string($email_footer_html) && trim($email_footer_html) !== '';
?>
                        </td>
					</tr>
				</table>
			</td>
		</tr>
		<?php if ($email_show_footer_html) : ?>
			<tr>
				<td
					class="fs-mail-weekly-report-footer-text"
					style="
              padding: 16px 0 0;
              color: #94a3b8;
              font-size: 13px;
			  text-wrap: balance;
			  text-align: center;
            ">
					<div style="
					max-width: 540px;
					padding: 0 24px;
					margin: 0 auto;
				">
						<?= wp_kses_post($email_footer_html) ?>
					</div>
				</td>
			</tr>
		<?php endif; ?>
	</table>
</body>

</html>