<mail>
	<title>{@config->fname}: A post ha sido reported</title>
	<comment>Hello {@row->name},

the post with the title {@info->title} ha sido reported by {%my->name}. The following message ha sido added:
----------------------------------------------------------------------
{$message}
----------------------------------------------------------------------

You can find the post here:
{@config->furl}/showtopic.php?action=jumpto&id={@info->topic_id}&topic_id={@info->id}

Yours sincerely,
Your {@config->fname} Team
{@config->furl}</comment>
</mail>