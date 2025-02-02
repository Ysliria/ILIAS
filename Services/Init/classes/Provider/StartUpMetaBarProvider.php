<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\Init\Provider;

use ILIAS\GlobalScreen\Identification\IdentificationInterface;
use ILIAS\GlobalScreen\Scope\MetaBar\Provider\AbstractStaticMetaBarProvider;
use Psr\Http\Message\UriInterface;
use ILIAS\Data\Factory as DataFactory;

class StartUpMetaBarProvider extends AbstractStaticMetaBarProvider
{
    /**
     * @inheritDoc
     */
    public function getMetaBarItems(): array
    {
        $factory = $this->dic->ui()->factory();
        $request = $this->dic->http()->request();
        $languages = $this->dic->language()->getInstalledLanguages();

        $if = function (string $id): IdentificationInterface {
            return $this->if->identifier($id);
        };

        $txt = function (string $id): string {
            return $this->dic->language()->txt($id);
        };

        // Login-Button
        // Only visible, if not on login-page but not logged in
        $target_str = '';
        if (isset($request->getQueryParams()['ref_id']) && $ref_id = $request->getQueryParams()['ref_id']) {
            $type = \ilObject::_lookupType((int) $ref_id, true);
            if ($type !== 'root') {  // see bug #30710
                $target_str = 'target=' . \ilObject::_lookupType((int) $ref_id, true) . '_' . (int) $ref_id . '&';
            }
        } elseif (isset($request->getQueryParams()['target']) && $target = $request->getQueryParams()['target']) {
            $target = rawurlencode($target);        // see #32789
            $target_str = 'target=' . $target . '&';
        }

        $login_glyph = $factory->symbol()->glyph()->login();

        $current_language = $this->dic->user()->getCurrentLanguage() ?: $this->dic->language()->getLangKey();

        $login = $this->meta_bar
            ->topLinkItem($if('login'))
            ->withAction(
                'login.php?' . $target_str . 'client_id=' . rawurlencode(
                    CLIENT_ID
                ) . '&cmd=force_login&lang=' . $current_language
            )
            ->withSymbol($login_glyph)
            ->withPosition(2)
            ->withTitle($txt('log_in'))
            ->withAvailableCallable(function () {
                return !$this->isUserLoggedIn();
            })
            ->withVisibilityCallable(function () use ($request) {
                return !$this->isUserOnLoginPage($request->getUri());
            });

        // Language-Selection
        $language_selection = $this->meta_bar
            ->topParentItem($if('language_selection'))
            ->withSymbol($factory->symbol()->glyph()->language())
            ->withPosition(1)
            ->withAvailableCallable(function () {
                return !$this->isUserLoggedIn();
            })
            ->withVisibilityCallable(function () use ($languages) {
                return \count($languages) > 1;
            })
            ->withTitle($txt('language'));

        $base = $this->getBaseURL($request->getUri());
        $dataFactory = new DataFactory();

        foreach ($languages as $lang_key) {
            $link = $this->appendUrlParameterString($base, 'lang=' . $lang_key);
            $language_name = $this->dic->language()->_lookupEntry($lang_key, 'meta', 'meta_l_' . $lang_key);

            $language_icon = $factory
                ->symbol()
                ->icon()
                ->standard('none', $language_name)
                ->withAbbreviation($lang_key);

            $s = $this->meta_bar
                ->linkItem($if($lang_key))
                ->withSymbol($language_icon)
                ->withAction($link)
                ->withContentLanguage($dataFactory->languageTag($lang_key))
                ->withLanguageForTargetedResource($dataFactory->languageTag($lang_key))
                ->withTitle($language_name);

            $language_selection->appendChild($s);
        }

        return [
            $login,
            $language_selection,
        ];
    }

    private function isUserLoggedIn(): bool
    {
        return (!$this->dic->user()->isAnonymous() && $this->dic->user()->getId() !== 0);
    }

    private function isUserOnLoginPage(UriInterface $uri): bool
    {
        return preg_match('%^.*/login.php$%', $uri->getPath()) === 1;
    }

    private function appendUrlParameterString(string $existing_url, string $addition): string
    {
        $url = (\is_int(strpos($existing_url, '?')))
            ? $existing_url . '&' . $addition
            : $existing_url . '?' . $addition;

        $url = str_replace('?&', '?', $url);

        return $url;
    }

    private function getBaseURL(UriInterface $uri): string
    {
        $base = substr($uri->__toString(), strrpos($uri->__toString(), '/') + 1);

        return rtrim(preg_replace('/([&?])lang=[a-z]{2}([&$])/', '$1', $base), '?&');
    }
}
