<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Hail\Cache\Simple;

/**
 * Void cache driver. The cache could be of use in tests where you don`t need to cache anything.
 *
 * @link   www.doctrine-project.org
 * @since  1.5
 * @author Kotlyar Maksim <kotlyar.maksim@gmail.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
class Void extends AbstractAdapter
{
	/**
	 * {@inheritDoc}
	 */
	protected function doGet(string $key)
	{
		return null;
	}
	/**
	 * {@inheritDoc}
	 */
	protected function doHas(string $key)
	{
		return false;
	}
	/**
	 * {@inheritDoc}
	 */
	protected function doSet(string $key, $value, int $ttl = 0)
	{
		return true;
	}
	/**
	 * {@inheritDoc}
	 */
	protected function doDelete(string $key)
	{
		return true;
	}
	/**
	 * {@inheritDoc}
	 */
	protected function doClear()
	{
		return true;
	}
}
